<?php

declare(strict_types=1);

namespace Fundly\Integration\Runtime\Outbox;

use DateInterval;
use Fundly\Integration\Runtime\Errors\ErrorClass;
use Fundly\Integration\Runtime\Errors\IntegrationException;
use Fundly\Integration\Runtime\Resilience\RetryPolicy;
use Fundly\Shared\Audit\Actor;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Audit\AuditOutcome;
use Fundly\Shared\Audit\AuditTrail;
use Fundly\Shared\Clock\Clock;
use Fundly\Shared\Http\RequestContext;
use Fundly\Shared\Security\SystemIdentity;
use Fundly\Shared\Tenancy\TenantContext;
use Illuminate\Database\ConnectionInterface;
use Throwable;

/**
 * At-least-once delivery of outbox messages (FR-CBA-008, TRD §11).
 *
 * Messages are *claimed* with FOR UPDATE SKIP LOCKED and a lease (available_at
 * pushed forward, attempts incremented) in a short transaction; the handler
 * then runs outside any transaction. A crash after the provider applied the
 * effect but before the outcome was recorded leads to a re-delivery once the
 * lease expires; handlers use idempotency keys and lookup-before-retry so the
 * effect still happens exactly once.
 *
 * Outcome by error class: retryable → backoff with full jitter until
 * max_attempts, then parked (requires_intervention); non_retryable → failed;
 * requires_intervention → parked; business_rejection → rejected.
 */
final class OutboxDispatcher
{
    private const LEASE_SECONDS = 300;

    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly TenantContext $tenant,
        private readonly OutboxHandlerRegistry $handlers,
        private readonly RetryPolicy $retry,
        private readonly Clock $clock,
        private readonly AuditTrail $audit,
        private readonly RequestContext $request,
    ) {
    }

    /** @return array{dispatched: int, retried: int, parked: int, failed: int, rejected: int} */
    public function dispatchDue(string $tenantId, int $limit = 50): array
    {
        return $this->tenant->run($tenantId, function () use ($limit): array {
            $stats = ['dispatched' => 0, 'retried' => 0, 'parked' => 0, 'failed' => 0, 'rejected' => 0];
            foreach ($this->claim($limit) as $message) {
                $stats[$this->deliver($message)]++;
            }

            return $stats;
        });
    }

    /** @return list<OutboxMessage> */
    private function claim(int $limit): array
    {
        $now = $this->clock->now();

        return $this->db->transaction(function () use ($limit, $now): array {
            $rows = $this->db->select(
                "select id from outbox_messages where status = 'pending' and available_at <= ? order by created_at limit ? for update skip locked",
                [$now, $limit],
            );
            $out = [];
            foreach ($rows as $row) {
                $claimed = $this->db->selectOne(
                    'update outbox_messages set attempts = attempts + 1, available_at = ?, updated_at = ? where id = ? returning *',
                    [$now->add(new DateInterval('PT'.self::LEASE_SECONDS.'S')), $now, $row->id],
                );
                if (! is_object($claimed)) {
                    continue;
                }
                /** @var array<string, mixed> $payload */
                $payload = json_decode((string) $claimed->payload, true, 64, JSON_THROW_ON_ERROR);
                $out[] = new OutboxMessage(
                    (string) $claimed->id,
                    (string) $claimed->tenant_id,
                    (string) $claimed->topic,
                    $payload,
                    is_string($claimed->idempotency_key) ? $claimed->idempotency_key : null,
                    (int) $claimed->attempts,
                    is_string($claimed->correlation_id) ? $claimed->correlation_id : null,
                );
            }

            return $out;
        });
    }

    private function deliver(OutboxMessage $m): string
    {
        $previousCorrelation = $this->request->correlationId();
        if ($m->correlationId !== null) {
            $this->request->setCorrelationId($m->correlationId);
        }
        try {
            $result = $this->handlers->for($m->topic)->handle($m);
            $this->finish($m, 'dispatched', null, ['result' => $result]);

            return 'dispatched';
        } catch (IntegrationException $e) {
            return $this->onError($m, $e->errorClass(), $e->canonicalCode, $e->getMessage());
        } catch (Throwable $e) {
            return $this->onError($m, ErrorClass::RequiresIntervention, 'INTEGRATION.OUTBOX.HANDLER_ERROR', $e::class.': '.$e->getMessage());
        } finally {
            $this->request->setCorrelationId($previousCorrelation);
        }
    }

    private function onError(OutboxMessage $m, ErrorClass $class, string $code, string $message): string
    {
        $max = (int) $this->db->table('outbox_messages')->where('id', $m->id)->value('max_attempts');
        if ($class === ErrorClass::Retryable && $m->attempt < $max) {
            $delayMs = $this->retry->delayMs($m->attempt);
            $this->db->table('outbox_messages')->where('id', $m->id)->update([
                'status' => 'pending',
                'available_at' => $this->clock->now()->add(new DateInterval('PT'.max(0, intdiv($delayMs, 1000)).'S')),
                'last_error_class' => $class->value,
                'last_error_code' => $code,
                'last_error_message' => mb_substr($message, 0, 2000),
                'updated_at' => $this->clock->now(),
            ]);

            return 'retried';
        }

        $status = match ($class) {
            ErrorClass::NonRetryable => 'failed',
            ErrorClass::BusinessRejection => 'rejected',
            default => 'parked', // retryable but exhausted, or requires_intervention
        };
        $this->finish($m, $status, ['class' => $class === ErrorClass::Retryable ? ErrorClass::RequiresIntervention->value : $class->value, 'code' => $code, 'message' => $message], null);

        return match ($status) {
            'failed' => 'failed',
            'rejected' => 'rejected',
            default => 'parked',
        };
    }

    /**
     * @param  array{class: string, code: string, message: string}|null  $error
     * @param  array<string, mixed>|null  $result
     */
    private function finish(OutboxMessage $m, string $status, ?array $error, ?array $result): void
    {
        $now = $this->clock->now();
        $this->db->table('outbox_messages')->where('id', $m->id)->update(array_filter([
            'status' => $status,
            'dispatched_at' => $status === 'dispatched' ? $now : null,
            'last_error_class' => $error['class'] ?? null,
            'last_error_code' => $error['code'] ?? null,
            'last_error_message' => isset($error['message']) ? mb_substr($error['message'], 0, 2000) : null,
            'updated_at' => $now,
        ], static fn ($v): bool => $v !== null));

        $this->audit->record(new AuditEntry(
            action: 'integration.outbox.'.$status,
            outcome: $status === 'dispatched' ? AuditOutcome::Success : AuditOutcome::Failure,
            entityType: 'outbox_message',
            entityId: $m->id,
            after: ['topic' => $m->topic, 'attempt' => $m->attempt, 'error' => $error, 'result' => $result],
        ), Actor::system(SystemIdentity::Outbox));
    }
}

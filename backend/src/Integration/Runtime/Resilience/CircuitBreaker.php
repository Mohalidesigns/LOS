<?php

declare(strict_types=1);

namespace Fundly\Integration\Runtime\Resilience;

use Fundly\Shared\Audit\Actor;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Audit\AuditTrail;
use Fundly\Shared\Clock\Clock;
use Fundly\Shared\Id\UuidV7;
use Fundly\Shared\Security\SystemIdentity;
use Fundly\Shared\Tenancy\TenantContext;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Log;

/**
 * Per-binding circuit breaker (FR-CBA-010): opens after N consecutive
 * failures, half-opens after the cool-down to let one probe through, closes on
 * success. Every transition is recorded; opening raises an operational alert
 * (critical log + audit event attributed to system:integration-runtime).
 */
final class CircuitBreaker
{
    public function __construct(
        private readonly BreakerStore $store,
        private readonly Clock $clock,
        private readonly ConnectionInterface $db,
        private readonly TenantContext $tenant,
        private readonly AuditTrail $audit,
        private readonly int $failureThreshold,
        private readonly int $openSeconds,
    ) {}

    public function allows(string $key): bool
    {
        $now = $this->clock->now()->getTimestamp();
        [$before, $after] = $this->store->update($key, function (BreakerState $s) use ($now): BreakerState {
            if ($s->state === BreakerState::OPEN && $s->openedAt !== null && $now - $s->openedAt >= $this->openSeconds) {
                return new BreakerState(BreakerState::HALF_OPEN, $s->consecutiveFailures, $s->openedAt);
            }

            return $s;
        });
        $this->recordTransition($key, $before, $after, 'cool-down elapsed; allowing a probe');

        return $after->state !== BreakerState::OPEN;
    }

    public function recordSuccess(string $key): void
    {
        [$before, $after] = $this->store->update($key, static fn (BreakerState $s): BreakerState => BreakerState::closed());
        $this->recordTransition($key, $before, $after, 'probe succeeded');
    }

    public function recordFailure(string $key, string $reason): void
    {
        $now = $this->clock->now()->getTimestamp();
        [$before, $after] = $this->store->update($key, function (BreakerState $s) use ($now): BreakerState {
            $failures = $s->consecutiveFailures + 1;
            if ($s->state === BreakerState::HALF_OPEN || $failures >= $this->failureThreshold) {
                return new BreakerState(BreakerState::OPEN, $failures, $now);
            }

            return new BreakerState(BreakerState::CLOSED, $failures, null);
        });
        $this->recordTransition($key, $before, $after, $reason);
    }

    public function state(string $key): BreakerState
    {
        return $this->store->get($key);
    }

    private function recordTransition(string $key, BreakerState $before, BreakerState $after, string $reason): void
    {
        if ($before->state === $after->state) {
            return;
        }
        $this->db->table('circuit_events')->insert([
            'id' => UuidV7::generate(),
            'tenant_id' => $this->tenant->requireId(),
            'breaker_key' => $key,
            'from_state' => $before->state,
            'to_state' => $after->state,
            'reason' => mb_substr($reason, 0, 1000),
            'occurred_at' => $this->clock->now(),
        ]);
        if ($after->state === BreakerState::OPEN) {
            Log::critical('integration.circuit.opened', ['breaker' => $key, 'failures' => $after->consecutiveFailures, 'reason' => $reason]);
            $this->audit->record(new AuditEntry(
                action: 'integration.circuit.opened',
                entityType: 'circuit_breaker',
                entityId: $key,
                before: ['state' => $before->state],
                after: ['state' => $after->state, 'consecutive_failures' => $after->consecutiveFailures, 'reason' => $reason],
            ), Actor::system(SystemIdentity::IntegrationRuntime));
        }
    }
}

<?php

declare(strict_types=1);

namespace Fundly\Shared\Audit;

use DateTimeZone;
use Fundly\Shared\Clock\Clock;
use Fundly\Shared\Database\Row;
use Fundly\Shared\Http\RequestContext;
use Fundly\Shared\Id\UuidV7;
use Fundly\Shared\Pii\PiiMasker;
use Fundly\Shared\Tenancy\TenantContext;
use Illuminate\Database\ConnectionInterface;

/**
 * Hash-chained, append-only audit writer (FR-AUD-001..005, TRD §5.4).
 *
 * Writes are serialised per tenant through a transaction-scoped advisory lock
 * so `seq` is gap-free and every row links to its predecessor. If the caller
 * is already in a transaction (the command bus), the event commits or rolls
 * back atomically with the state change it describes.
 */
final class PostgresAuditTrail implements AuditTrail
{
    private const LOCK_NAMESPACE = 41_300;

    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly TenantContext $tenant,
        private readonly ActorProvider $actors,
        private readonly RequestContext $request,
        private readonly PiiMasker $masker,
        private readonly Clock $clock,
    ) {}

    public function record(AuditEntry $entry, ?Actor $actor = null): string
    {
        $tenantId = $this->tenant->requireId();
        $actor ??= $this->actors->current();

        return $this->db->transaction(function () use ($entry, $actor, $tenantId): string {
            $this->db->select('select pg_advisory_xact_lock(?, hashtext(?))', [self::LOCK_NAMESPACE, $tenantId]);

            $last = Row::one($this->db->selectOne(
                'select seq, hash from audit_events where tenant_id = ? order by seq desc limit 1',
                [$tenantId],
            ));
            $seq = $last !== null ? ((int) $last->seq) + 1 : 1;
            $prev = $last !== null ? (string) $last->hash : HashChain::GENESIS;

            $row = [
                'id' => UuidV7::generate(),
                'tenant_id' => $tenantId,
                'seq' => $seq,
                'occurred_at' => $this->clock->now()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.uP'),
                'actor_type' => $actor->type->value,
                'actor_id' => $actor->id,
                'actor_roles' => array_values($actor->roles),
                'effective_permissions_hash' => $actor->permissionsHash,
                'on_behalf_of' => $actor->onBehalfOf,
                'source_ip' => $this->request->sourceIp(),
                'user_agent' => $this->request->userAgent(),
                'device_id' => $this->request->deviceId(),
                'action' => $entry->action,
                'permission' => $entry->permission,
                'outcome' => $entry->outcome->value,
                'entity_type' => $entry->entityType,
                'entity_id' => $entry->entityId,
                'before' => self::normalise($this->masker->mask($entry->before)),
                'after' => self::normalise($this->masker->mask($entry->after)),
                'reason_code' => $entry->reasonCode,
                'reason_text' => $entry->reasonText,
                'correlation_id' => $this->request->correlationId(),
                'step_up_ref' => $entry->stepUpRef,
                'prev_hash' => $prev,
            ];
            $row['hash'] = HashChain::compute($row);

            $insert = $row;
            foreach (['actor_roles', 'before', 'after'] as $json) {
                $insert[$json] = $row[$json] === null ? null : json_encode($row[$json], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
            }
            $this->db->table('audit_events')->insert($insert);

            return $row['id'];
        });
    }

    /**
     * Round-trip through JSON so the hashed structure is exactly what jsonb
     * will give back to the verifier (objects become arrays, etc.).
     *
     * @param  array<array-key, mixed>|null  $data
     * @return array<array-key, mixed>|null
     */
    private static function normalise(?array $data): ?array
    {
        if ($data === null) {
            return null;
        }
        $decoded = json_decode(
            json_encode($data, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION),
            true,
            512,
            JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING,
        );

        return is_array($decoded) ? $decoded : null;
    }
}

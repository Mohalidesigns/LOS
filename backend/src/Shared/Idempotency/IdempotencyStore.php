<?php

declare(strict_types=1);

namespace Fundly\Shared\Idempotency;

use DateInterval;
use Fundly\Shared\Clock\Clock;
use Fundly\Shared\Id\UuidV7;
use Fundly\Shared\Tenancy\TenantContext;
use Illuminate\Database\ConnectionInterface;

/**
 * Stores key + principal + request hash with the original response for 72 h
 * (TRD §10.1). Reservation is an INSERT .. ON CONFLICT DO NOTHING, so two
 * concurrent requests with one key cannot both execute.
 */
final class IdempotencyStore
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly TenantContext $tenant,
        private readonly Clock $clock,
        private readonly int $ttlHours,
    ) {}

    /** @return array{reserved: bool, record: ?object} */
    public function reserve(string $scope, string $principalId, string $key, string $requestHash): array
    {
        $tenantId = $this->tenant->requireId();
        $now = $this->clock->now();
        $this->purgeExpired($scope, $principalId, $key);

        $inserted = $this->db->affectingStatement(
            'insert into idempotency_keys (id, tenant_id, scope, principal_id, key, request_hash, status, created_at, expires_at)
             values (?, ?, ?, ?, ?, ?, ?, ?, ?) on conflict (tenant_id, scope, principal_id, key) do nothing',
            [UuidV7::generate(), $tenantId, $scope, $principalId, $key, $requestHash, 'in_progress', $now, $now->add(new DateInterval("PT{$this->ttlHours}H"))],
        );
        if ($inserted === 1) {
            return ['reserved' => true, 'record' => null];
        }

        $record = $this->db->table('idempotency_keys')
            ->where(['tenant_id' => $tenantId, 'scope' => $scope, 'principal_id' => $principalId, 'key' => $key])
            ->first();

        return ['reserved' => false, 'record' => $record];
    }

    /** @param array<string, string> $headers */
    public function complete(string $scope, string $principalId, string $key, int $status, array $headers, string $body): void
    {
        $this->db->table('idempotency_keys')
            ->where(['tenant_id' => $this->tenant->requireId(), 'scope' => $scope, 'principal_id' => $principalId, 'key' => $key])
            ->update([
                'status' => 'completed',
                'response_status' => $status,
                'response_headers' => json_encode($headers, JSON_THROW_ON_ERROR),
                'response_body' => $body,
            ]);
    }

    /** Releases a reservation so the client may retry (used on 5xx). */
    public function release(string $scope, string $principalId, string $key): void
    {
        $this->db->table('idempotency_keys')
            ->where(['tenant_id' => $this->tenant->requireId(), 'scope' => $scope, 'principal_id' => $principalId, 'key' => $key])
            ->delete();
    }

    private function purgeExpired(string $scope, string $principalId, string $key): void
    {
        $this->db->table('idempotency_keys')
            ->where(['tenant_id' => $this->tenant->requireId(), 'scope' => $scope, 'principal_id' => $principalId, 'key' => $key])
            ->where('expires_at', '<', $this->clock->now())
            ->delete();
    }
}

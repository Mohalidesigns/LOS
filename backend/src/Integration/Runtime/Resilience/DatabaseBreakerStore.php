<?php

declare(strict_types=1);

namespace Fundly\Integration\Runtime\Resilience;

use Fundly\Shared\Clock\Clock;
use Fundly\Shared\Tenancy\TenantContext;
use Illuminate\Database\ConnectionInterface;

/** Breaker state in PostgreSQL (row lock): the fallback when Redis is absent, and the test store. */
final class DatabaseBreakerStore implements BreakerStore
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly TenantContext $tenant,
        private readonly Clock $clock,
    ) {
    }

    public function update(string $key, callable $mutate): array
    {
        return $this->db->transaction(function () use ($key, $mutate): array {
            $tenantId = $this->tenant->requireId();
            $this->db->insert(
                'insert into circuit_breakers (tenant_id, breaker_key, state, consecutive_failures, updated_at) values (?, ?, ?, 0, ?) on conflict do nothing',
                [$tenantId, $key, BreakerState::CLOSED, $this->clock->now()],
            );
            $row = $this->db->table('circuit_breakers')->where(['tenant_id' => $tenantId, 'breaker_key' => $key])->lockForUpdate()->first();
            $before = self::hydrate($row);
            $after = $mutate($before);
            $this->db->table('circuit_breakers')->where(['tenant_id' => $tenantId, 'breaker_key' => $key])->update([
                'state' => $after->state,
                'consecutive_failures' => $after->consecutiveFailures,
                'opened_at' => $after->openedAt === null ? null : (new \DateTimeImmutable)->setTimestamp($after->openedAt),
                'updated_at' => $this->clock->now(),
            ]);

            return [$before, $after];
        });
    }

    public function get(string $key): BreakerState
    {
        return self::hydrate($this->db->table('circuit_breakers')->where(['tenant_id' => $this->tenant->requireId(), 'breaker_key' => $key])->first());
    }

    private static function hydrate(?object $row): BreakerState
    {
        if ($row === null) {
            return BreakerState::closed();
        }

        return new BreakerState(
            (string) $row->state,
            (int) $row->consecutive_failures,
            $row->opened_at === null ? null : (new \DateTimeImmutable((string) $row->opened_at))->getTimestamp(),
        );
    }
}

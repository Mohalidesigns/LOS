<?php

declare(strict_types=1);

namespace Fundly\Integration\Simulators\CoreBanking;

use Fundly\Shared\Clock\Clock;
use Fundly\Shared\Database\Row;
use Fundly\Shared\Id\UuidV7;
use Fundly\Shared\Tenancy\TenantContext;
use Illuminate\Database\ConnectionInterface;

/** Durable simulator state (tenant-scoped by RLS) so effects survive across workers and retries. */
final class SimulatorStore
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly TenantContext $tenant,
        private readonly Clock $clock,
    ) {}

    /** @return array<string, mixed>|null */
    public function get(string $kind, string $key): ?array
    {
        $json = $this->db->table('cba_simulator_records')->where(['kind' => $kind, 'record_key' => $key])->value('data');
        $data = is_string($json) ? json_decode($json, true) : null;

        return is_array($data) ? $data : null;
    }

    /**
     * Inserts if absent. Returns true if this call created the record.
     *
     * @param  array<string, mixed>  $data
     */
    public function putIfAbsent(string $kind, string $key, array $data): bool
    {
        return $this->db->affectingStatement(
            'insert into cba_simulator_records (id, tenant_id, kind, record_key, data, created_at) values (?, ?, ?, ?, ?::jsonb, ?) on conflict (tenant_id, kind, record_key) do nothing',
            [UuidV7::generate(), $this->tenant->requireId(), $kind, $key, json_encode($data, JSON_THROW_ON_ERROR), $this->clock->now()],
        ) === 1;
    }

    /** @return list<array<string, mixed>> */
    public function all(string $kind): array
    {
        $out = [];
        foreach ($this->db->table('cba_simulator_records')->where('kind', $kind)->orderBy('created_at')->pluck('data') as $json) {
            $d = is_string($json) ? json_decode($json, true) : null;
            if (is_array($d)) {
                $out[] = $d;
            }
        }

        return $out;
    }

    /** Atomic counter; returns the new value. */
    public function increment(string $kind, string $key): int
    {
        $row = Row::one($this->db->selectOne(
            "insert into cba_simulator_records (id, tenant_id, kind, record_key, data, created_at)
             values (?, ?, ?, ?, '{\"n\": 1}'::jsonb, ?)
             on conflict (tenant_id, kind, record_key)
             do update set data = jsonb_build_object('n', (cba_simulator_records.data->>'n')::int + 1)
             returning (data->>'n')::int as n",
            [UuidV7::generate(), $this->tenant->requireId(), $kind, $key, $this->clock->now()],
        ));

        return $row !== null ? (int) $row->n : 1;
    }
}

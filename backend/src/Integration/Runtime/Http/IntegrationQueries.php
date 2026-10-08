<?php

declare(strict_types=1);

namespace Fundly\Integration\Runtime\Http;

use Fundly\Integration\Runtime\AdapterRegistry;
use Fundly\Integration\Runtime\Models\AdapterBinding;
use Fundly\Shared\Exceptions\NotFound;
use Fundly\Shared\Http\CursorPaginator;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\Request;

final class IntegrationQueries
{
    public function __construct(private readonly AdapterRegistry $registry, private readonly ConnectionInterface $db)
    {
    }

    /** @return array<string, mixed> */
    public function bindings(Request $request): array
    {
        return CursorPaginator::paginate(AdapterBinding::query(), $request, fn (AdapterBinding $b): array => $this->presentBinding($b));
    }

    /** @return array<string, mixed> */
    public function binding(string $id): array
    {
        $b = AdapterBinding::query()->find($id) ?? throw new NotFound('Adapter binding not found.');

        return $this->presentBinding($b) + ['manifest' => $this->registry->get($b->adapter_key)->manifest->toArray()];
    }

    /** @return array<string, mixed> */
    public function calls(Request $request): array
    {
        $q = $this->db->table('integration_calls');
        $filter = $request->query('filter');
        $filter = is_array($filter) ? $filter : [];
        foreach (['operation', 'outcome', 'correlation_id', 'idempotency_key', 'binding_id'] as $f) {
            if (isset($filter[$f]) && is_string($filter[$f])) {
                $q->where($f, $filter[$f]);
            }
        }

        return CursorPaginator::paginate($q, $request, static function (object $r): array {
            $json = static fn (mixed $v): mixed => is_string($v) ? json_decode($v, true) : null;

            return [
                'id' => $r->id,
                'binding_id' => $r->binding_id,
                'port' => $r->port,
                'operation' => $r->operation,
                'adapter_key' => $r->adapter_key,
                'adapter_version' => $r->adapter_version,
                'contract_version' => $r->contract_version,
                'idempotency_key' => $r->idempotency_key,
                'correlation_id' => $r->correlation_id,
                'attempt' => (int) $r->attempt,
                'request' => $json($r->request),
                'response' => $json($r->response),
                'outcome' => $r->outcome,
                'error_code' => $r->error_code,
                'latency_ms' => (int) $r->latency_ms,
                'started_at' => (new \DateTimeImmutable((string) $r->started_at))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z'),
            ];
        });
    }

    /** @return array<string, mixed> */
    private function presentBinding(AdapterBinding $b): array
    {
        return [
            'id' => $b->id,
            'legal_entity_id' => $b->legal_entity_id,
            'port' => $b->port,
            'adapter_key' => $b->adapter_key,
            'adapter_version' => $b->adapter_version,
            'processing_location' => $b->processing_location,
            'status' => $b->status,
            'config' => $b->config,
            'fault_script' => $b->fault_script,
            'allow_in_production' => $b->allow_in_production,
            'created_at' => $b->created_at->toIso8601ZuluString('microsecond'),
        ];
    }
}

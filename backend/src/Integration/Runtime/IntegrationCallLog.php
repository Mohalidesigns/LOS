<?php

declare(strict_types=1);

namespace Fundly\Integration\Runtime;

use DateTimeImmutable;
use Fundly\Shared\Http\RequestContext;
use Fundly\Shared\Id\UuidV7;
use Fundly\Shared\Pii\PiiMasker;
use Fundly\Shared\Tenancy\TenantContext;
use Illuminate\Database\ConnectionInterface;

/**
 * Every provider interaction: request, response, latency, correlation id,
 * adapter version and outcome, PII-masked (FR-CBA-016, FR-AUD-008). The table
 * is append-only for the runtime role and retained per audit policy.
 */
final class IntegrationCallLog
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly TenantContext $tenant,
        private readonly RequestContext $request,
        private readonly PiiMasker $masker,
    ) {}

    /**
     * @param  array<string, mixed>  $request
     * @param  array<array-key, mixed>|null  $response
     */
    public function record(
        ?string $bindingId,
        string $port,
        string $operation,
        string $adapterKey,
        string $adapterVersion,
        ?string $contractVersion,
        ?string $idempotencyKey,
        int $attempt,
        array $request,
        ?array $response,
        string $outcome,
        ?string $errorCode,
        DateTimeImmutable $startedAt,
        DateTimeImmutable $finishedAt,
        int $latencyMs,
    ): void {
        $encode = static fn (?array $v): ?string => $v === null ? null : json_encode($v, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $this->db->table('integration_calls')->insert([
            'id' => UuidV7::generate(),
            'tenant_id' => $this->tenant->requireId(),
            'binding_id' => $bindingId,
            'port' => $port,
            'operation' => $operation,
            'adapter_key' => $adapterKey,
            'adapter_version' => $adapterVersion,
            'contract_version' => $contractVersion,
            'idempotency_key' => $idempotencyKey,
            'correlation_id' => $this->request->correlationId(),
            'attempt' => $attempt,
            'request' => $encode($this->masker->mask($request)),
            'response' => $encode($this->masker->mask($response)),
            'outcome' => $outcome,
            'error_code' => $errorCode,
            'latency_ms' => max(0, $latencyMs),
            'started_at' => $startedAt,
            'finished_at' => $finishedAt,
        ]);
    }
}

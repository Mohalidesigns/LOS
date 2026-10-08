<?php

declare(strict_types=1);

namespace Fundly\Shared\Outbox;

use Fundly\Shared\Audit\ActorProvider;
use Fundly\Shared\Bus\OutboxIntent;
use Fundly\Shared\Clock\Clock;
use Fundly\Shared\Http\RequestContext;
use Fundly\Shared\Id\UuidV7;
use Fundly\Shared\Tenancy\TenantContext;
use Illuminate\Database\ConnectionInterface;

final class DatabaseOutbox implements Outbox
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly TenantContext $tenant,
        private readonly Clock $clock,
        private readonly RequestContext $request,
        private readonly ActorProvider $actors,
        private readonly int $defaultMaxAttempts,
    ) {
    }

    public function add(OutboxIntent $intent): string
    {
        $id = UuidV7::generate();
        $now = $this->clock->now();
        $this->db->table('outbox_messages')->insert([
            'id' => $id,
            'tenant_id' => $this->tenant->requireId(),
            'topic' => $intent->topic,
            'aggregate_type' => $intent->aggregateType,
            'aggregate_id' => $intent->aggregateId,
            'payload' => json_encode($intent->payload, JSON_THROW_ON_ERROR),
            'idempotency_key' => $intent->idempotencyKey,
            'status' => 'pending',
            'attempts' => 0,
            'max_attempts' => $intent->maxAttempts > 0 ? $intent->maxAttempts : $this->defaultMaxAttempts,
            'available_at' => $now,
            'correlation_id' => $this->request->correlationId(),
            'created_by' => $this->actors->current()->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $id;
    }
}

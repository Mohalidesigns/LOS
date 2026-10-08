<?php

declare(strict_types=1);

namespace Fundly\Integration\Runtime\Outbox;

/** Read-only view of a claimed outbox row handed to a handler. */
final readonly class OutboxMessage
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public string $id,
        public string $tenantId,
        public string $topic,
        public array $payload,
        public ?string $idempotencyKey,
        public int $attempt,
        public ?string $correlationId,
    ) {}

    /** True if an earlier delivery may already have reached the provider. */
    public function isRedelivery(): bool
    {
        return $this->attempt > 1;
    }
}

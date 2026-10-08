<?php

declare(strict_types=1);

namespace Fundly\Shared\Bus;

/** An external effect to deliver at-least-once after the transaction commits (FR-CBA-008). */
final readonly class OutboxIntent
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public string $topic,
        public array $payload,
        public ?string $aggregateType = null,
        public ?string $aggregateId = null,
        public ?string $idempotencyKey = null,
        public int $maxAttempts = 0,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace Fundly\Shared\Audit;

/** What happened. before/after are masked by the trail before storage (FR-CMP-036). */
final readonly class AuditEntry
{
    /**
     * @param  array<array-key, mixed>|null  $before
     * @param  array<array-key, mixed>|null  $after
     */
    public function __construct(
        public string $action,
        public AuditOutcome $outcome = AuditOutcome::Success,
        public ?string $entityType = null,
        public ?string $entityId = null,
        public ?array $before = null,
        public ?array $after = null,
        public ?string $permission = null,
        public ?string $reasonCode = null,
        public ?string $reasonText = null,
        public ?string $stepUpRef = null,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace Fundly\Modules\Approval\Contracts;

/** A condition an approver attached; becomes a tracked CP or CS (FR-APV-009). */
final readonly class ApprovalConditionView
{
    public function __construct(
        public string $id,
        public string $kind,
        public string $description,
        public string $status,
        public string $levelCode,
    ) {}
}

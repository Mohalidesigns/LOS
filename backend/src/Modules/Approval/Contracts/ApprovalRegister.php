<?php

declare(strict_types=1);

namespace Fundly\Modules\Approval\Contracts;

interface ApprovalRegister
{
    /** The latest approval request that still stands (superseded ones excluded). */
    public function current(string $applicationId): ?ApprovalView;
}

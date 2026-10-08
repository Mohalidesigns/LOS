<?php

declare(strict_types=1);

namespace Fundly\Modules\Application\Contracts;

use Fundly\Shared\Security\Principal;

/**
 * Lets other modules move an application along the canonical graph
 * (screening cleared → Documentation, decision → Approved, booked …) without
 * touching its internals. Runs through the command bus, so it is audited and
 * transactional like any other change.
 */
interface ApplicationLifecycle
{
    public function advance(string $applicationId, CanonicalStatus $to, string $reasonCode, ?string $note, Principal $actor): void;
}

<?php

declare(strict_types=1);

namespace Fundly\Modules\Conditions\Contracts;

/** What disbursement asks before release (FR-CPR-002/003). */
interface ConditionsGate
{
    /** @return list<string> why release is blocked; empty when clear */
    public function releaseBlockers(string $applicationId): array;

    /** The account the latest passing, in-date check verified; null when there is none. */
    public function verifiedDestination(string $applicationId): ?VerifiedDestination;
}

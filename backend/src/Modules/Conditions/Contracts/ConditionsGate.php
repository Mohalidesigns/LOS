<?php

declare(strict_types=1);

namespace Fundly\Modules\Conditions\Contracts;

/** What disbursement asks before release (FR-CPR-002/003). */
interface ConditionsGate
{
    /** @return list<string> why release is blocked; empty when clear */
    public function releaseBlockers(string $applicationId): array;

    /**
     * Re-run the pre-disbursement check now, against the account the latest
     * passing check verified (release time, D-053), and return what still blocks.
     *
     * @return list<string>
     */
    public function revalidate(string $applicationId, string $tenantId): array;

    /** The account the latest passing, in-date check verified; null when there is none. */
    public function verifiedDestination(string $applicationId): ?VerifiedDestination;
}

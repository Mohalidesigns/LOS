<?php

declare(strict_types=1);

namespace Fundly\Modules\Compliance\Contracts;

/** What disbursement-side modules need from compliance (FR-CPR-003/004). */
interface ComplianceGate
{
    public function kyc(string $applicationId): ?KycStatus;

    /**
     * Re-screen every party on the application now (trigger `pre_disbursement`)
     * and report the alerts still open, pending confirmation or confirmed.
     */
    public function rescreen(string $applicationId, string $tenantId): ScreeningOutcome;

    /**
     * Check and record the single-obligor limit now, with the obligor's
     * current exposure from the core banking system (FR-CMP-023).
     *
     * @param  'approval'|'pre_disbursement'  $stage
     */
    public function regulatoryLimits(string $applicationId, string $stage, string $actorId): RegulatoryLimitCheck;
}

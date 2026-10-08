<?php

declare(strict_types=1);

namespace Fundly\Modules\Credit\Contracts;

interface CreditFile
{
    /** Latest decision with exception summary and the latest memo's recommendation. */
    public function latestDecision(string $applicationId): ?CreditDecisionView;

    /** True when the primary applicant has a bureau report that is still within its validity window (FR-CMP-020). */
    public function hasValidBureauReport(string $applicationId): bool;
}

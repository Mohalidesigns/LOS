<?php

declare(strict_types=1);

namespace Fundly\Modules\Disbursement\Domain;

/**
 * Lifecycle of one disbursement instruction (TRD §6.2 Disbursing sub-states, G-14f):
 *
 *   pending_release → (checker releases) pending_cba → booked
 *                                           ↘ failed_retryable ↔ pending_cba (retry)
 *                                           ↘ failed_intervention → pending_cba (retry after fixing)
 *                                           ↘ compensated (definite rejection after the account was opened; account closed)
 *   pending_release → cancelled (maker withdraws before release)
 */
enum DisbursementStatus: string
{
    case PendingRelease = 'pending_release';
    case PendingCba = 'pending_cba';
    case FailedRetryable = 'failed_retryable';
    case FailedIntervention = 'failed_intervention';
    case Compensated = 'compensated';
    case Booked = 'booked';
    case Cancelled = 'cancelled';

    /** Still occupies the application: no second instruction while one of these exists. */
    public function isLive(): bool
    {
        return ! in_array($this, [self::Compensated, self::Cancelled], true);
    }

    /** In the disbursement exception queue (PR-08). */
    public function isException(): bool
    {
        return in_array($this, [self::FailedRetryable, self::FailedIntervention, self::Compensated], true);
    }

    public function canRetry(): bool
    {
        return $this === self::FailedRetryable || $this === self::FailedIntervention;
    }
}

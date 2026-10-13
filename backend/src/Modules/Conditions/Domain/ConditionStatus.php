<?php

declare(strict_types=1);

namespace Fundly\Modules\Conditions\Domain;

use Fundly\Shared\Exceptions\DomainRuleViolation;

/** Lifecycle of a CP/CS item (FR-CPR-001/002). */
enum ConditionStatus: string
{
    case Open = 'open';
    case WaiverPending = 'waiver_pending';
    case Satisfied = 'satisfied';
    case Waived = 'waived';

    /** Counts against disbursement while it is a mandatory precedent. */
    public function isOutstanding(): bool
    {
        return $this === self::Open || $this === self::WaiverPending;
    }

    public function assertCanSatisfy(): void
    {
        if ($this !== self::Open && $this !== self::WaiverPending) {
            throw new DomainRuleViolation("This condition is already {$this->value}.");
        }
    }

    /** Waiver-pending may be re-requested: the checker may have rejected the earlier request. */
    public function assertCanWaive(): void
    {
        if (! $this->isOutstanding()) {
            throw new DomainRuleViolation("This condition is already {$this->value}.");
        }
    }
}

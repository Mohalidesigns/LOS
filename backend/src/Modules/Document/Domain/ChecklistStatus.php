<?php

declare(strict_types=1);

namespace Fundly\Modules\Document\Domain;

use Fundly\Shared\Exceptions\DomainRuleViolation;

/** Checklist item statuses (FR-DOC-007). `extracted` arrives with IDP in P2. */
enum ChecklistStatus: string
{
    case NotReceived = 'not_received';
    case Received = 'received';
    case UnderReview = 'under_review';
    case Verified = 'verified';
    case Rejected = 'rejected';
    case Waived = 'waived';
    case Expired = 'expired';

    /** Satisfies a mandatory requirement for the Documentation → Assessment gate. */
    public function satisfies(): bool
    {
        return $this === self::Verified || $this === self::Waived;
    }

    public function assertCanReceive(): void
    {
        if ($this === self::Waived) {
            throw new DomainRuleViolation('This item was waived; it no longer takes documents.');
        }
    }

    public function assertCanReview(): void
    {
        if (! in_array($this, [self::Received, self::UnderReview], true)) {
            throw new DomainRuleViolation("Only a received document can be verified or rejected; this item is {$this->value}.");
        }
    }

    public function assertCanWaive(): void
    {
        if ($this->satisfies()) {
            throw new DomainRuleViolation("This item is already {$this->value}.");
        }
    }
}

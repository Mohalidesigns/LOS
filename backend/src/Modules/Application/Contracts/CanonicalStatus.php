<?php

declare(strict_types=1);

namespace Fundly\Modules\Application\Contracts;

/**
 * Platform-owned canonical application status (TRD §6.2, BRD §18, D-013,
 * LOS-FR-282/283). Tenants relabel workflow stages; they never add statuses.
 */
enum CanonicalStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case PreQualified = 'pre_qualified';
    case KycScreening = 'kyc_screening';
    case Documentation = 'documentation';
    case Assessment = 'assessment';
    case Recommended = 'recommended';
    case Approval = 'approval';
    case Approved = 'approved';
    case CounterOffered = 'counter_offered';
    case OfferIssued = 'offer_issued';
    case Accepted = 'accepted';
    case ConditionsPrecedent = 'conditions_precedent';
    case ReadyForDisbursement = 'ready_for_disbursement';
    case Disbursing = 'disbursing';
    case Booked = 'booked';
    case Declined = 'declined';
    case Expired = 'expired';
    // cross-cutting (LOS-FR-283)
    case OnHold = 'on_hold';
    case ReturnedForRework = 'returned_for_rework';
    case Withdrawn = 'withdrawn';
    case Cancelled = 'cancelled';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Booked, self::Declined, self::Expired, self::Withdrawn, self::Cancelled], true);
    }

    /** Position on the main path; cross-cutting and terminal side states have none. */
    public function rank(): ?int
    {
        $order = [
            self::Draft, self::Submitted, self::PreQualified, self::KycScreening, self::Documentation, self::Assessment,
            self::Recommended, self::Approval, self::Approved, self::CounterOffered, self::OfferIssued, self::Accepted,
            self::ConditionsPrecedent, self::ReadyForDisbursement, self::Disbursing, self::Booked,
        ];
        $i = array_search($this, $order, true);

        return $i === false ? null : $i;
    }

    /** Still before approval: data may be amended (FR-APP-005). */
    public function isPreApproval(): bool
    {
        $rank = $this->rank();

        return $this === self::ReturnedForRework || ($rank !== null && $rank <= (int) self::Recommended->rank());
    }

    public function label(): string
    {
        return ucfirst(str_replace('_', ' ', $this->value));
    }
}

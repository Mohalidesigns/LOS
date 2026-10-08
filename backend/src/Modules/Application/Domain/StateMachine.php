<?php

declare(strict_types=1);

namespace Fundly\Modules\Application\Domain;

use Fundly\Modules\Application\Contracts\CanonicalStatus as S;

/**
 * The canonical transition graph (TRD §6.2). Pure; the configuration
 * validator and the aggregate both ask it whether a move is legal.
 */
final class StateMachine
{
    /** @return array<string, list<S>> */
    public static function graph(): array
    {
        return [
            S::Draft->value => [S::Submitted],
            S::Submitted->value => [S::PreQualified, S::Declined],
            S::PreQualified->value => [S::KycScreening],
            S::KycScreening->value => [S::Documentation],
            S::Documentation->value => [S::Assessment],
            S::Assessment->value => [S::Recommended],
            S::Recommended->value => [S::Approval],
            S::Approval->value => [S::Approval, S::Approved, S::Declined, S::CounterOffered],
            S::Approved->value => [S::OfferIssued, S::Expired],
            S::CounterOffered->value => [S::OfferIssued],
            S::OfferIssued->value => [S::Accepted, S::Assessment, S::Expired],
            S::Accepted->value => [S::ConditionsPrecedent],
            S::ConditionsPrecedent->value => [S::ReadyForDisbursement],
            S::ReadyForDisbursement->value => [S::Disbursing],
            S::Disbursing->value => [S::Disbursing, S::Booked],
            S::Declined->value => [S::Assessment],
            S::Booked->value => [],
            S::Expired->value => [],
            S::Withdrawn->value => [],
            S::Cancelled->value => [],
            S::OnHold->value => [],
            S::ReturnedForRework->value => [],
        ];
    }

    public static function allows(S $from, S $to): bool
    {
        return in_array($to, self::graph()[$from->value] ?? [], true);
    }

    /** Hold / rework / withdraw / cancel are available up to and including ReadyForDisbursement (TRD §6.2). */
    public static function crossCuttingAllowedFrom(S $from): bool
    {
        $rank = $from->rank();

        return $rank !== null && $rank <= (int) S::ReadyForDisbursement->rank();
    }

    /** Withdraw/cancel may also end an application that is on hold or back for rework. */
    public static function canClose(S $from): bool
    {
        return self::crossCuttingAllowedFrom($from) || in_array($from, [S::OnHold, S::ReturnedForRework], true);
    }

    /** Rework goes back to a named earlier stage that has been passed (FR-WFL-007). */
    public static function canReturnTo(S $from, S $target): bool
    {
        $f = $from->rank();
        $t = $target->rank();

        return self::crossCuttingAllowedFrom($from) && $f !== null && $t !== null && $t < $f && $t >= (int) S::Draft->rank();
    }
}

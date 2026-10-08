<?php

declare(strict_types=1);

namespace Fundly\Modules\Credit\Application;

use Fundly\Modules\Application\Contracts\ApplicationSummary;
use Fundly\Modules\Application\Contracts\TransitionGuard;
use Fundly\Modules\Credit\Infrastructure\Models\CreditMemo;
use Fundly\Shared\Exceptions\NotFound;

/**
 * "Recommend" needs a complete, current credit file (FR-CMP-020/027): a
 * valid bureau report, a decision, and a memo written on that decision.
 */
final class RecommendGuard implements TransitionGuard
{
    public function __construct(private readonly CreditFileLoader $file) {}

    public function blockers(ApplicationSummary $application, string $action): array
    {
        if ($action !== 'recommend') {
            return [];
        }
        $blockers = [];
        try {
            if ($this->file->validBureau($application->id, $this->file->primaryPartyId($application)) === null) {
                $blockers[] = 'A current credit bureau report on the primary applicant is required.';
            }
        } catch (NotFound) {
            $blockers[] = 'A current credit bureau report on the primary applicant is required.';
        }
        $decision = $this->file->latestDecision($application->id);
        if ($decision === null) {
            $blockers[] = 'Run the credit decision.';
        } elseif (! CreditMemo::query()->where('application_id', $application->id)->where('decision_id', $decision->id)->exists()) {
            $blockers[] = 'Write the credit memo on the latest decision.';
        }

        return $blockers;
    }
}

<?php

declare(strict_types=1);

namespace Fundly\Modules\Credit\Application;

use Fundly\Modules\Application\Contracts\ApplicationSummary;
use Fundly\Modules\Credit\Infrastructure\Models\BureauReport;
use Fundly\Modules\Credit\Infrastructure\Models\DecisionSnapshot;
use Fundly\Modules\Credit\Infrastructure\Models\PolicyException;
use Fundly\Modules\Party\Contracts\PartyDirectory;

/** Auto-populated credit memo sections (FR-CRD-013), frozen into each saved version. */
final class MemoComposer
{
    public function __construct(private readonly PartyDirectory $parties) {}

    /** @return list<array{key: string, title: string, content: array<string, mixed>}> */
    public function sections(ApplicationSummary $app, ?DecisionSnapshot $d): array
    {
        $names = [];
        foreach ($this->parties->findMany(array_keys($app->applicants)) as $p) {
            $names[] = ['name' => $p->displayName, 'type' => $p->type, 'role' => $app->applicants[$p->id] ?? null];
        }
        $sections = [
            ['key' => 'applicants', 'title' => 'Applicants', 'content' => ['parties' => $names, 'segment' => $app->segment, 'channel' => $app->channel]],
            ['key' => 'facility', 'title' => 'Facility requested', 'content' => ['reference' => $app->reference, 'amount' => $app->requestedAmount?->toArray(), 'tenor_months' => $app->tenorMonths]],
        ];
        if ($d === null) {
            return $sections;
        }
        $o = $d->outputs;
        $bureau = $d->bureau_report_id === null ? null : BureauReport::query()->find($d->bureau_report_id);
        $sections[] = ['key' => 'bureau', 'title' => 'Credit bureau', 'content' => $bureau === null ? [] : [
            'bureau' => $bureau->bureau, 'report_reference' => $bureau->report_reference, 'hit' => $bureau->hit,
            'score' => $bureau->profile['score'] ?? null, 'active_facilities' => $bureau->profile['active_facilities'] ?? 0,
            'total_outstanding' => $bureau->profile['total_outstanding'] ?? null, 'max_dpd_12m' => $bureau->profile['max_dpd_12m'] ?? 0,
            'pulled_at' => $bureau->pulled_at->toIso8601ZuluString(),
        ]];
        $sections[] = ['key' => 'decision', 'title' => 'Automated decision', 'content' => [
            'decision_id' => $d->id, 'outcome' => $d->outcome, 'risk_grade' => $d->risk_grade, 'reason_codes' => array_column((array) ($o['reason_codes'] ?? []), 'code'),
            'rule_set' => $d->rule_set_key.' v'.$d->rule_set_version_no, 'evaluator_version' => $d->evaluator_version, 'recommended_terms' => $o['recommended_terms'] ?? null,
        ]];
        $sections[] = ['key' => 'affordability', 'title' => 'Affordability', 'content' => (array) ($o['affordability'] ?? [])];
        $sections[] = ['key' => 'exceptions', 'title' => 'Policy exceptions', 'content' => ['items' => array_map(
            static fn (PolicyException $e): array => ['reason_code' => $e->reason_code, 'severity' => $e->severity, 'justification' => $e->justification],
            array_values(PolicyException::query()->where('decision_id', $d->id)->orderBy('raised_at')->get()->all()),
        )]];

        return $sections;
    }
}

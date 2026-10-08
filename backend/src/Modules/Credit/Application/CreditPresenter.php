<?php

declare(strict_types=1);

namespace Fundly\Modules\Credit\Application;

use Fundly\Modules\Credit\Infrastructure\Models\BureauReport;
use Fundly\Modules\Credit\Infrastructure\Models\CreditMemo;
use Fundly\Modules\Credit\Infrastructure\Models\DecisionSnapshot;
use Fundly\Modules\Credit\Infrastructure\Models\PolicyException;
use Fundly\Shared\Money\Money;
use Illuminate\Support\Carbon;

final class CreditPresenter
{
    /** @return array<string, mixed> */
    public static function bureau(BureauReport $r, ?string $partyName, Carbon $now): array
    {
        return [
            'id' => $r->id,
            'application_id' => $r->application_id,
            'party_id' => $r->party_id,
            'party_name' => $partyName,
            'bureau' => $r->bureau,
            'report_reference' => $r->report_reference,
            'pulled_at' => $r->pulled_at->toIso8601ZuluString('microsecond'),
            'valid_until' => $r->valid_until->toIso8601ZuluString('microsecond'),
            'is_valid' => $r->valid_until->greaterThan($now),
            'hit' => $r->hit,
            'profile' => $r->profile,
            'pulled_by' => $r->pulled_by,
        ];
    }

    /**
     * @param  list<PolicyException>  $exceptions
     * @return array<string, mixed>
     */
    public static function decision(DecisionSnapshot $d, array $exceptions, bool $latest): array
    {
        $o = $d->outputs;

        return [
            'id' => $d->id,
            'application_id' => $d->application_id,
            'sequence' => $d->sequence,
            'outcome' => $d->outcome,
            'risk_grade' => $d->risk_grade,
            'requested_terms' => $o['requested_terms'] ?? null,
            'recommended_terms' => $o['recommended_terms'] ?? null,
            'reason_codes' => $o['reason_codes'] ?? [],
            'affordability' => $o['affordability'] ?? [],
            'rule_set' => ['key' => $d->rule_set_key, 'version_id' => $d->rule_set_version_id, 'version_no' => $d->rule_set_version_no],
            'evaluator_version' => $d->evaluator_version,
            'bureau_report_id' => $d->bureau_report_id,
            'facts' => (object) $d->facts,
            'trace' => $d->trace,
            'exceptions' => array_map(self::exception(...), $exceptions),
            'decided_by' => $d->decided_by,
            'decided_at' => $d->decided_at->toIso8601ZuluString('microsecond'),
            'is_latest' => $latest,
        ];
    }

    /** @return array<string, mixed> */
    public static function exception(PolicyException $e): array
    {
        return [
            'id' => $e->id, 'application_id' => $e->application_id, 'decision_id' => $e->decision_id, 'reason_code' => $e->reason_code,
            'justification' => $e->justification, 'evidence_ref' => $e->evidence_ref, 'severity' => $e->severity,
            'raised_by' => $e->raised_by, 'raised_at' => $e->raised_at->toIso8601ZuluString('microsecond'),
        ];
    }

    /** @return array<string, mixed> */
    public static function memo(CreditMemo $m, string $currency): array
    {
        return [
            'id' => $m->id, 'application_id' => $m->application_id, 'version_no' => $m->version_no, 'decision_id' => $m->decision_id,
            'sections' => $m->sections, 'narrative' => $m->narrative, 'recommendation' => $m->recommendation,
            'recommended_amount' => $m->recommended_amount === null ? null : Money::of($m->recommended_amount, $currency)->toArray(),
            'recommended_tenor_months' => $m->recommended_tenor_months, 'conditions' => $m->conditions,
            'authored_by' => $m->authored_by, 'authored_at' => $m->authored_at->toIso8601ZuluString('microsecond'),
        ];
    }
}

<?php

declare(strict_types=1);

namespace Fundly\Modules\Application\Application;

use Fundly\Modules\Application\Infrastructure\Models\ApplicantRecord;
use Fundly\Modules\Application\Infrastructure\Models\ApplicationRecord;
use Fundly\Shared\Money\Money;

final class ApplicationPresenter
{
    /** @return array<string, mixed> */
    public static function summary(ApplicationRecord $r): array
    {
        return [
            'id' => $r->id,
            'reference' => $r->reference,
            'status' => $r->canonical_status,
            'product' => ['id' => $r->product_id, 'key' => $r->product_key, 'name' => $r->product_name, 'version_id' => $r->product_version_id],
            'segment' => $r->segment,
            'channel' => $r->channel,
            'primary_applicant' => ['party_id' => $r->primary_party_id, 'display_name' => $r->primary_applicant_name],
            'requested_amount' => $r->requested_amount === null ? null : Money::of($r->requested_amount, $r->currency)->toArray(),
            'currency' => $r->currency,
            'tenor_months' => $r->tenor_months,
            'legal_entity_id' => $r->legal_entity_id,
            'org_unit_id' => $r->org_unit_id,
            'originator_id' => $r->originator_id,
            'submitted_at' => $r->submitted_at?->toIso8601ZuluString('microsecond'),
            'status_changed_at' => $r->status_changed_at->toIso8601ZuluString('microsecond'),
            'created_at' => $r->created_at->toIso8601ZuluString('microsecond'),
            'version' => $r->version,
        ];
    }

    /**
     * @param  list<ApplicantRecord>  $applicants
     * @param  array{percent: int, missing: list<string>, checklist: list<array{code: string, name: string, mandatory: bool}>}  $completeness
     * @return array<string, mixed>
     */
    public static function detail(ApplicationRecord $r, array $applicants, array $completeness): array
    {
        return self::summary($r) + [
            'purpose' => $r->purpose,
            'repayment_frequency' => $r->repayment_frequency,
            'data' => (object) $r->data,
            'resume_to' => $r->resume_to,
            'return_to' => $r->return_to,
            'closed_at' => $r->closed_at?->toIso8601ZuluString('microsecond'),
            'close_reason_code' => $r->close_reason_code,
            'expires_at' => $r->expires_at?->toIso8601ZuluString('microsecond'),
            'applicants' => array_map(static fn (ApplicantRecord $a): array => [
                'party_id' => $a->party_id, 'role' => $a->role, 'party_type' => $a->party_type, 'display_name' => $a->display_name,
            ], $applicants),
            'completeness' => $completeness,
        ];
    }
}

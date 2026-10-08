<?php

declare(strict_types=1);

namespace Fundly\Modules\Application\Infrastructure;

use Fundly\Modules\Application\Contracts\ApplicationReader;
use Fundly\Modules\Application\Contracts\ApplicationSummary;
use Fundly\Modules\Application\Contracts\CanonicalStatus;
use Fundly\Modules\Application\Infrastructure\Models\ApplicantRecord;
use Fundly\Modules\Application\Infrastructure\Models\ApplicationRecord;
use Fundly\Shared\Money\Money;

final class StoreBackedApplicationReader implements ApplicationReader
{
    public function find(string $applicationId): ?ApplicationSummary
    {
        $r = preg_match('/^[0-9a-f-]{36}$/', $applicationId) === 1 ? ApplicationRecord::query()->find($applicationId) : null;
        if ($r === null) {
            return null;
        }
        $applicants = [];
        foreach (ApplicantRecord::query()->where('application_id', $r->id)->get() as $a) {
            $applicants[$a->party_id] = $a->role;
        }

        return new ApplicationSummary(
            id: $r->id,
            reference: $r->reference,
            status: CanonicalStatus::from($r->canonical_status),
            legalEntityId: $r->legal_entity_id,
            orgUnitId: $r->org_unit_id,
            productId: $r->product_id,
            productVersionId: $r->product_version_id,
            segment: $r->segment,
            channel: $r->channel,
            originatorId: $r->originator_id,
            requestedAmount: $r->requested_amount === null ? null : Money::of($r->requested_amount, $r->currency),
            tenorMonths: $r->tenor_months,
            applicants: $applicants,
            version: $r->version,
        );
    }
}

<?php

declare(strict_types=1);

namespace Fundly\Modules\Disbursement\Infrastructure;

use Fundly\Modules\Disbursement\Contracts\BookedFacility;
use Fundly\Modules\Disbursement\Contracts\DisbursementRegister;
use Fundly\Modules\Disbursement\Infrastructure\Models\Disbursement;
use Fundly\Shared\Money\Money;

final class DatabaseDisbursementRegister implements DisbursementRegister
{
    public function booked(string $applicationId): ?BookedFacility
    {
        $d = Disbursement::query()->where('application_id', $applicationId)->where('status', 'booked')->orderByDesc('sequence')->first();
        if ($d === null || $d->loan_account_no === null || $d->booked_at === null) {
            return null;
        }

        return new BookedFacility(
            disbursementId: $d->id,
            loanAccountNo: $d->loan_account_no,
            cbaCustomerId: (string) $d->cba_customer_id,
            postingRef: (string) $d->posting_ref,
            principal: Money::of($d->principal_amount, $d->currency),
            net: Money::of($d->net_amount, $d->currency),
            fees: $d->fees,
            valueDate: $d->value_date->toDateString(),
            bookedAt: $d->booked_at->toDateTimeImmutable(),
            schedule: $d->cba_schedule ?? [],
            adviceDocumentId: $d->advice_document_id,
        );
    }
}

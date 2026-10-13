<?php

declare(strict_types=1);

namespace Fundly\Modules\Disbursement\Contracts\Events;

use Fundly\Shared\Bus\DomainEvent;

/** The loan is on the CBA books and disbursed; handover (P1-HND-01) starts here. */
final readonly class FacilityBooked implements DomainEvent
{
    public function __construct(public string $tenantId, public string $applicationId, public string $disbursementId, public string $loanAccountNo, public string $offerId) {}

    public function name(): string
    {
        return 'facility.booked';
    }

    public function payload(): array
    {
        return ['tenant_id' => $this->tenantId, 'application_id' => $this->applicationId, 'disbursement_id' => $this->disbursementId, 'loan_account_no' => $this->loanAccountNo, 'offer_id' => $this->offerId];
    }
}

<?php

declare(strict_types=1);

namespace Fundly\Modules\Offer\Contracts\Events;

use Fundly\Shared\Bus\DomainEvent;

/** The customer accepted an offer and the executed agreement is on file; CPs (P1-CPR-01) start from here. */
final readonly class OfferAccepted implements DomainEvent
{
    public function __construct(public string $tenantId, public string $applicationId, public string $offerId, public string $agreementId) {}

    public function name(): string
    {
        return 'offer.accepted';
    }

    public function payload(): array
    {
        return ['tenant_id' => $this->tenantId, 'application_id' => $this->applicationId, 'offer_id' => $this->offerId, 'agreement_id' => $this->agreementId];
    }
}

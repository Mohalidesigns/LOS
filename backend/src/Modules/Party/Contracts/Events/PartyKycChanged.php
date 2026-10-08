<?php

declare(strict_types=1);

namespace Fundly\Modules\Party\Contracts\Events;

use Fundly\Shared\Bus\DomainEvent;

/** An identity verification or consent changed; KYC gates that depend on the party re-evaluate. */
final readonly class PartyKycChanged implements DomainEvent
{
    public function __construct(public string $tenantId, public string $partyId, public string $change) {}

    public function name(): string
    {
        return 'party.kyc_changed';
    }

    public function payload(): array
    {
        return ['tenant_id' => $this->tenantId, 'party_id' => $this->partyId, 'change' => $this->change];
    }
}

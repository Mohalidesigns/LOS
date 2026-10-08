<?php

declare(strict_types=1);

namespace Fundly\Modules\Party\Contracts;

/** KYC-relevant facts about a party, for the CDD gate and screening. */
final readonly class PartyKycProfile
{
    /**
     * @param  list<string>  $verifiedIdentityTypes
     * @param  list<string>  $failedIdentityTypes
     * @param  list<array{party_id: string, display_name: string, role: string}>  $relatedIndividuals  directors, signatories and beneficial owners
     */
    public function __construct(
        public string $id,
        public string $type,
        public string $displayName,
        public ?string $dateOfBirth,
        public ?string $nationality,
        public ?string $registrationNumber,
        public array $verifiedIdentityTypes,
        public array $failedIdentityTypes,
        public array $relatedIndividuals,
    ) {}
}

<?php

declare(strict_types=1);

namespace Fundly\Modules\Party\Contracts;

/** Read access to parties for other modules (applications, screening, credit). */
interface PartyDirectory
{
    public function find(string $partyId): ?PartySummary;

    /**
     * @param  list<string>  $partyIds
     * @return array<string, PartySummary> keyed by id; unknown ids are omitted
     */
    public function findMany(array $partyIds): array;

    /** Decrypted contact email for a delivery the caller is about to make (e.g. an offer letter); null when none is held. */
    public function contactEmail(string $partyId): ?string;

    /** Decrypted contact phone (E.164, e.g. +2348030000000) for a delivery the caller is about to make; null when none is held. */
    public function contactPhone(string $partyId): ?string;

    /** Identity verification state and related individuals (KYC / screening input). */
    public function kycProfile(string $partyId): ?PartyKycProfile;
}

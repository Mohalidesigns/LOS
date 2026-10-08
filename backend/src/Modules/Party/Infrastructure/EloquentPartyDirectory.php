<?php

declare(strict_types=1);

namespace Fundly\Modules\Party\Infrastructure;

use Fundly\Modules\Party\Contracts\PartyDirectory;
use Fundly\Modules\Party\Contracts\PartyKycProfile;
use Fundly\Modules\Party\Contracts\PartySummary;
use Fundly\Modules\Party\Infrastructure\Models\Party;
use Fundly\Modules\Party\Infrastructure\Models\PartyIdentity;
use Fundly\Modules\Party\Infrastructure\Models\PartyRelationship;
use Fundly\Shared\Crypto\FieldEncryptor;

final class EloquentPartyDirectory implements PartyDirectory
{
    public function __construct(private readonly FieldEncryptor $crypto) {}

    public function find(string $partyId): ?PartySummary
    {
        return $this->findMany([$partyId])[$partyId] ?? null;
    }

    public function findMany(array $partyIds): array
    {
        $ids = array_values(array_filter($partyIds, static fn (string $id): bool => preg_match('/^[0-9a-f-]{36}$/', $id) === 1));
        if ($ids === []) {
            return [];
        }
        $out = [];
        foreach (Party::query()->whereIn('id', $ids)->get() as $p) {
            $out[$p->id] = new PartySummary($p->id, $p->type, $p->display_name, $p->org_unit_id, $p->status);
        }

        return $out;
    }

    public function kycProfile(string $partyId): ?PartyKycProfile
    {
        $p = preg_match('/^[0-9a-f-]{36}$/', $partyId) === 1 ? Party::query()->find($partyId) : null;
        if ($p === null) {
            return null;
        }
        $identities = PartyIdentity::query()->where('party_id', $p->id)->get();
        $related = [];
        $rels = PartyRelationship::query()->where('party_id', $p->id)->whereIn('role', ['director', 'beneficial_owner', 'signatory'])->get();
        $names = Party::query()->whereIn('id', $rels->pluck('related_party_id')->all())->where('type', 'individual')->pluck('display_name', 'id');
        foreach ($rels as $r) {
            if (isset($names[$r->related_party_id])) {
                $related[] = ['party_id' => $r->related_party_id, 'display_name' => (string) $names[$r->related_party_id], 'role' => $r->role];
            }
        }

        return new PartyKycProfile(
            id: $p->id,
            type: $p->type,
            displayName: $p->display_name,
            dateOfBirth: $p->date_of_birth_enc === null ? null : $this->crypto->decrypt($p->date_of_birth_enc, 'party.date_of_birth'),
            nationality: $p->nationality,
            registrationNumber: $p->registration_number,
            verifiedIdentityTypes: array_values(array_map(strval(...), $identities->where('verification_status', 'verified')->pluck('type')->all())),
            failedIdentityTypes: array_values(array_map(strval(...), $identities->where('verification_status', 'failed')->pluck('type')->all())),
            relatedIndividuals: $related,
        );
    }
}

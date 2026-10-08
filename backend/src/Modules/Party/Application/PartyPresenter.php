<?php

declare(strict_types=1);

namespace Fundly\Modules\Party\Application;

use Fundly\Modules\Party\Infrastructure\Models\Party;
use Fundly\Modules\Party\Infrastructure\Models\PartyIdentity;
use Fundly\Shared\Crypto\FieldEncryptor;
use Fundly\Shared\Http\ETag;
use Fundly\Shared\Pii\PiiMasker;

/** Serialises parties with PII masked by default (FR-CMP-036, TRD §5.3). */
final class PartyPresenter
{
    public function __construct(private readonly FieldEncryptor $crypto) {}

    /** @return array<string, mixed> */
    public function summary(Party $p): array
    {
        return [
            'id' => $p->id,
            'type' => $p->type,
            'display_name' => $p->display_name,
            'org_unit_id' => $p->org_unit_id,
            'status' => $p->status,
            'registration_number' => $p->registration_number,
            'created_at' => $p->created_at->toIso8601ZuluString('microsecond'),
        ];
    }

    /**
     * @param  iterable<PartyIdentity>  $identities
     * @return array<string, mixed>
     */
    public function detail(Party $p, iterable $identities): array
    {
        $address = $p->address_enc === null ? null : json_decode($this->crypto->decrypt($p->address_enc, 'party.address'), true);
        $ids = [];
        foreach ($identities as $i) {
            $ids[] = [
                'id' => $i->id,
                'type' => $i->type,
                'value_masked' => $i->value_masked,
                'verification_status' => $i->verification_status,
                'verified_at' => $i->verified_at?->toIso8601ZuluString('microsecond'),
                'provider' => $i->provider,
                'match_score' => $i->match_score,
            ];
        }

        return $this->summary($p) + [
            'cba_customer_id' => $p->cba_customer_id,
            'first_name' => $p->first_name,
            'middle_name' => $p->middle_name,
            'last_name' => $p->last_name,
            'gender' => $p->gender,
            'date_of_birth_masked' => $this->masked($p->date_of_birth_enc, 'party.date_of_birth'),
            'nationality' => $p->nationality,
            'incorporation_date' => $p->incorporation_date?->toDateString(),
            'sector' => $p->sector,
            'phone_masked' => $this->masked($p->phone_enc, 'party.phone'),
            'email_masked' => $this->masked($p->email_enc, 'party.email'),
            'tin_masked' => $this->masked($p->tin_enc, 'party.tin'),
            'address' => is_array($address) ? ['city' => $address['city'] ?? null, 'state' => $address['state'] ?? null, 'country' => $address['country'] ?? null, 'line1_masked' => isset($address['line1']) && is_string($address['line1']) ? PiiMasker::maskValue($address['line1']) : null] : null,
            'identities' => $ids,
            'updated_at' => $p->updated_at->toIso8601ZuluString('microsecond'),
        ];
    }

    public static function etag(Party $p): string
    {
        return ETag::of($p->id, $p->updated_at->format('Uu'));
    }

    private function masked(?string $ciphertext, string $field): ?string
    {
        return $ciphertext === null ? null : PiiMasker::maskValue($this->crypto->decrypt($ciphertext, $field));
    }
}

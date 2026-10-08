<?php

declare(strict_types=1);

namespace Fundly\Modules\Party\Infrastructure;

use Fundly\Modules\Party\Contracts\IdentityNumberResolver;
use Fundly\Modules\Party\Infrastructure\Models\Party;
use Fundly\Modules\Party\Infrastructure\Models\PartyIdentity;
use Fundly\Shared\Crypto\FieldEncryptor;

final class EncryptedIdentityNumberResolver implements IdentityNumberResolver
{
    public function __construct(private readonly FieldEncryptor $crypto) {}

    /** @param list<string> $types */
    public function resolve(string $partyId, array $types): ?array
    {
        foreach ($types as $type) {
            if ($type === 'rc_number') {
                $rc = Party::query()->whereKey($partyId)->value('registration_number');
                if (is_string($rc) && $rc !== '') {
                    return ['type' => 'rc_number', 'value' => $rc];
                }

                continue;
            }
            $row = PartyIdentity::query()->where('party_id', $partyId)->where('type', $type)->first();
            if ($row !== null) {
                return ['type' => $type, 'value' => $this->crypto->decrypt($row->value_enc, 'party_identity.'.$type)];
            }
        }

        return null;
    }
}

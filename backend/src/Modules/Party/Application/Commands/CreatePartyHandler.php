<?php

declare(strict_types=1);

namespace Fundly\Modules\Party\Application\Commands;

use Fundly\Modules\Party\Application\PartyMatcher;
use Fundly\Modules\Party\Application\PartyPresenter;
use Fundly\Modules\Party\Domain\IdentityType;
use Fundly\Modules\Party\Domain\NameNormaliser;
use Fundly\Modules\Party\Domain\PhoneNumber;
use Fundly\Modules\Party\Infrastructure\Models\Party;
use Fundly\Modules\Party\Infrastructure\Models\PartyIdentity;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Crypto\FieldEncryptor;
use Fundly\Shared\Exceptions\ValidationFailed;
use Fundly\Shared\Pii\PiiMasker;
use Illuminate\Support\Facades\DB;

final class CreatePartyHandler implements CommandHandler
{
    public function __construct(
        private readonly FieldEncryptor $crypto,
        private readonly PartyMatcher $matcher,
        private readonly PartyPresenter $presenter,
    ) {}

    /** @return array{data: array<string, mixed>, etag: string} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof CreateParty);
        $identities = [];
        foreach ($command->identities as $i => $identity) {
            $type = IdentityType::from($identity['type']);
            $value = strtoupper(preg_replace('/\s+/', '', $identity['value']) ?? '');
            if (! $type->isWellFormed($value)) {
                throw ValidationFailed::with(["identities.{$i}.value" => "A {$type->value} must be {$type->formatHint()}."]);
            }
            $identities[] = ['type' => $type, 'value' => $value];
        }
        if ($command->orgUnitId !== null && DB::table('org_units')->where('id', $command->orgUnitId)->doesntExist()) {
            throw ValidationFailed::with(['org_unit_id' => 'Unknown org unit.']);
        }

        $individual = $command->type === 'individual';
        $displayName = $individual
            ? trim(implode(' ', array_filter([$command->firstName, $command->middleName, $command->lastName])))
            : trim((string) $command->companyName);
        $rc = $command->registrationNumber === null ? null : strtoupper(preg_replace('/\s+/', '', $command->registrationNumber) ?? '');
        $phone = $command->phone === null ? null : PhoneNumber::normalise($command->phone);
        $email = $command->email === null ? null : mb_strtolower(trim($command->email));

        $duplicates = $this->matcher->match([
            'name' => $displayName, 'phone' => $phone, 'email' => $email, 'tin' => $command->tin, 'registration_number' => $rc,
            'identities' => array_map(static fn (array $i): array => ['type' => $i['type']->value, 'value' => $i['value']], $identities),
        ]);

        $party = new Party;
        $party->forceFill([
            'type' => $command->type,
            'display_name' => $displayName,
            'name_normalised' => NameNormaliser::normalise($displayName),
            'org_unit_id' => $command->orgUnitId,
            'status' => 'active',
            'cba_customer_id' => $command->cbaCustomerId,
            'first_name' => $individual ? $command->firstName : null,
            'middle_name' => $individual ? $command->middleName : null,
            'last_name' => $individual ? $command->lastName : null,
            'gender' => $individual ? $command->gender : null,
            'date_of_birth_enc' => $individual && $command->dateOfBirth !== null ? $this->crypto->encrypt($command->dateOfBirth, 'party.date_of_birth') : null,
            'nationality' => $command->nationality,
            'registration_number' => $individual ? null : $rc,
            'incorporation_date' => $individual ? null : $command->incorporationDate,
            'sector' => $command->sector,
            'phone_enc' => $phone === null ? null : $this->crypto->encrypt($phone, 'party.phone'),
            'phone_bidx' => $phone === null ? null : $this->crypto->blindIndex($phone, 'party.phone'),
            'email_enc' => $email === null ? null : $this->crypto->encrypt($email, 'party.email'),
            'email_bidx' => $email === null ? null : $this->crypto->blindIndex($email, 'party.email'),
            'address_enc' => $command->address === null ? null : $this->crypto->encrypt((string) json_encode($command->address), 'party.address'),
            'tin_enc' => $command->tin === null ? null : $this->crypto->encrypt($command->tin, 'party.tin'),
            'tin_bidx' => $command->tin === null ? null : $this->crypto->blindIndex($command->tin, 'party.tin'),
            'created_by' => $context->principal->id,
        ])->save();

        $rows = [];
        foreach ($identities as $identity) {
            $row = new PartyIdentity;
            $row->forceFill([
                'party_id' => $party->id,
                'type' => $identity['type']->value,
                'value_enc' => $this->crypto->encrypt($identity['value'], 'party_identity.'.$identity['type']->value),
                'value_bidx' => $this->crypto->blindIndex($identity['value'], 'party_identity.'.$identity['type']->value),
                'value_masked' => PiiMasker::maskValue($identity['value']),
                'verification_status' => 'unverified',
            ])->save();
            $rows[] = $row;
        }
        $party->refresh();
        $data = $this->presenter->detail($party, $rows);
        $context->audit(new AuditEntry(action: $command->action(), entityType: 'party', entityId: $party->id, after: [
            'type' => $party->type, 'display_name' => $party->display_name, 'org_unit_id' => $party->org_unit_id,
            'identity_types' => array_map(static fn (array $i): string => $i['type']->value, $identities),
            'possible_duplicates' => array_column($duplicates, 'party_id'),
        ]));

        return ['data' => $data + ['possible_duplicates' => $duplicates], 'etag' => PartyPresenter::etag($party)];
    }
}

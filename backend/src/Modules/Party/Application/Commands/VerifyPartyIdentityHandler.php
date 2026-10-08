<?php

declare(strict_types=1);

namespace Fundly\Modules\Party\Application\Commands;

use Fundly\Integration\Ports\Identity\Dto\IdentityCheckRequest;
use Fundly\Integration\Ports\Identity\Dto\IdentityCheckResult;
use Fundly\Integration\Ports\Identity\IdentityOperations;
use Fundly\Integration\Ports\Identity\IdentityVerificationPort;
use Fundly\Integration\Runtime\IntegrationGateway;
use Fundly\Modules\Party\Application\PartyPresenter;
use Fundly\Modules\Party\Contracts\Events\PartyKycChanged;
use Fundly\Modules\Party\Infrastructure\Models\Party;
use Fundly\Modules\Party\Infrastructure\Models\PartyIdentity;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Clock\Clock;
use Fundly\Shared\Crypto\FieldEncryptor;
use Fundly\Shared\Exceptions\NotFound;
use Fundly\Shared\Exceptions\ValidationFailed;

/**
 * A read call through the gateway (inline retry, breaker, masked call log).
 * The provider reference, score and compared fields are stored; the provider
 * never returns raw identity data to the LOS.
 */
final class VerifyPartyIdentityHandler implements CommandHandler
{
    public function __construct(
        private readonly IntegrationGateway $gateway,
        private readonly FieldEncryptor $crypto,
        private readonly PartyPresenter $presenter,
        private readonly Clock $clock,
    ) {}

    /** @return array{data: array<string, mixed>, etag: string} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof VerifyPartyIdentity);
        $party = Party::query()->find($command->partyId) ?? throw new NotFound('Party not found.');
        $identity = PartyIdentity::query()->where('party_id', $party->id)->where('type', $command->identityType)->lockForUpdate()->first()
            ?? throw ValidationFailed::with(['identity_type' => "No {$command->identityType} is recorded for this party."]);
        $number = $this->crypto->decrypt($identity->value_enc, 'party_identity.'.$identity->type);
        $dob = $party->date_of_birth_enc === null ? null : $this->crypto->decrypt($party->date_of_birth_enc, 'party.date_of_birth');
        $phone = $party->phone_enc === null ? null : $this->crypto->decrypt($party->phone_enc, 'party.phone');

        /** @var IdentityCheckResult $result */
        $result = $this->gateway->call(
            IdentityVerificationPort::PORT,
            IdentityVerificationPort::OP_VERIFY,
            IdentityOperations::policy(),
            static function (object $adapter) use ($identity, $number, $party, $dob, $phone): IdentityCheckResult {
                assert($adapter instanceof IdentityVerificationPort);

                return $adapter->verify(new IdentityCheckRequest($identity->type, $number, $party->first_name, $party->last_name, $dob, $phone));
            },
            ['id_type' => $identity->type, 'bvn' => $identity->type === 'bvn' ? $number : null, 'nin' => $identity->type === 'nin' ? $number : null, 'party_id' => $party->id],
        );

        $before = ['verification_status' => $identity->verification_status];
        $identity->forceFill([
            'verification_status' => $result->outcome === IdentityCheckResult::VERIFIED ? 'verified' : 'failed',
            'verified_at' => $this->clock->now(),
            'provider' => 'identity_verification',
            'provider_reference' => $result->providerReference,
            'match_score' => $result->matchScore,
            'verification_details' => ['outcome' => $result->outcome, 'matched' => $result->matched, 'mismatched' => $result->mismatched],
        ])->save();
        $context->audit(new AuditEntry(action: $command->action(), entityType: 'party', entityId: $party->id, before: $before, after: [
            'identity_type' => $identity->type, 'outcome' => $result->outcome, 'match_score' => $result->matchScore, 'provider_reference' => $result->providerReference,
        ]));
        $context->raise(new PartyKycChanged($context->principal->tenantId, $party->id, 'identity.'.$identity->type));
        $party->touch();

        return ['data' => $this->presenter->detail($party->refresh(), PartyIdentity::query()->where('party_id', $party->id)->orderBy('type')->get()), 'etag' => PartyPresenter::etag($party)];
    }
}

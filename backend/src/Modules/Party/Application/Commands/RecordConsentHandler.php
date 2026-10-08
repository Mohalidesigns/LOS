<?php

declare(strict_types=1);

namespace Fundly\Modules\Party\Application\Commands;

use Fundly\Modules\Party\Application\PartyQueries;
use Fundly\Modules\Party\Contracts\ConsentRegistry;
use Fundly\Modules\Party\Contracts\Events\PartyKycChanged;
use Fundly\Modules\Party\Infrastructure\Models\Party;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Clock\Clock;
use Fundly\Shared\Exceptions\CodedConflict;
use Fundly\Shared\Exceptions\NotFound;
use Fundly\Shared\Id\UuidV7;
use Fundly\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

final class RecordConsentHandler implements CommandHandler
{
    public function __construct(
        private readonly ConsentRegistry $consents,
        private readonly Clock $clock,
        private readonly TenantContext $tenant,
        private readonly PartyQueries $queries,
    ) {}

    /** @return array{data: array<string, mixed>} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof RecordConsent);
        $party = Party::query()->lockForUpdate()->find($command->partyId) ?? throw new NotFound('Party not found.');
        $active = $this->consents->active($party->id, $command->purpose);
        if ($command->consentAction === 'grant' && $active) {
            throw new CodedConflict('consent-already-granted', "Consent for {$command->purpose} is already in force.");
        }
        if ($command->consentAction === 'withdraw' && ! $active) {
            throw new CodedConflict('consent-not-active', "There is no consent for {$command->purpose} to withdraw.");
        }
        DB::table('party_consents')->insert([
            'id' => UuidV7::generate(),
            'tenant_id' => $this->tenant->requireId(),
            'party_id' => $party->id,
            'purpose' => $command->purpose,
            'action' => $command->consentAction,
            'channel' => $command->channel,
            'terms_version' => $command->termsVersion,
            'evidence_ref' => $command->evidenceRef,
            'application_id' => $command->applicationId,
            'recorded_by' => $context->principal->id,
            'recorded_at' => $this->clock->now()->format('Y-m-d H:i:s.uP'),
        ]);
        $context->audit(new AuditEntry(action: $command->action(), entityType: 'party', entityId: $party->id, after: $command->data()));
        $context->raise(new PartyKycChanged($context->principal->tenantId, $party->id, 'consent.'.$command->purpose));

        return ['data' => $this->queries->consents($party->id)];
    }
}

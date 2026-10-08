<?php

declare(strict_types=1);

namespace Fundly\Modules\Compliance\Application\Commands;

use Fundly\Integration\Ports\Screening\Dto\ScreeningResult;
use Fundly\Integration\Ports\Screening\Dto\ScreeningSubject;
use Fundly\Integration\Ports\Screening\ScreeningOperations;
use Fundly\Integration\Ports\Screening\ScreeningPort;
use Fundly\Integration\Runtime\IntegrationGateway;
use Fundly\Modules\Application\Contracts\ApplicationReader;
use Fundly\Modules\Compliance\Contracts\Events\ApplicationScreened;
use Fundly\Modules\Compliance\Domain\AlertStatus;
use Fundly\Modules\Compliance\Infrastructure\Models\ScreeningAlert;
use Fundly\Modules\Party\Contracts\PartyDirectory;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Clock\Clock;
use Fundly\Shared\Exceptions\NotFound;
use Fundly\Shared\Id\UuidV7;
use Fundly\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

final class ScreenApplicationHandler implements CommandHandler
{
    public function __construct(
        private readonly ApplicationReader $applications,
        private readonly PartyDirectory $parties,
        private readonly IntegrationGateway $gateway,
        private readonly Clock $clock,
        private readonly TenantContext $tenant,
    ) {}

    /** @return array{runs: int, hits: int} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof ScreenApplication);
        $app = $this->applications->find($command->applicationId) ?? throw new NotFound('Application not found.');

        // Applicants plus their directors, signatories and beneficial owners.
        $subjects = [];
        foreach (array_keys($app->applicants) as $partyId) {
            $profile = $this->parties->kycProfile($partyId);
            if ($profile === null) {
                continue;
            }
            $subjects[$profile->id] = $profile;
            foreach ($profile->relatedIndividuals as $rel) {
                $related = $this->parties->kycProfile($rel['party_id']);
                if ($related !== null) {
                    $subjects[$related->id] = $related;
                }
            }
        }

        $runs = 0;
        $hits = 0;
        $now = $this->clock->now();
        foreach ($subjects as $subject) {
            /** @var ScreeningResult $result */
            $result = $this->gateway->call(
                ScreeningPort::PORT,
                ScreeningPort::OP_SCREEN,
                ScreeningOperations::policy(),
                static function (object $adapter) use ($subject, $app): ScreeningResult {
                    assert($adapter instanceof ScreeningPort);

                    return $adapter->screen(new ScreeningSubject(
                        reference: $app->reference.':'.$subject->id,
                        kind: $subject->type === 'individual' ? 'individual' : 'organisation',
                        name: $subject->displayName,
                        dateOfBirth: $subject->dateOfBirth,
                        nationality: $subject->nationality,
                        registrationNumber: $subject->registrationNumber,
                    ));
                },
                ['subject_party_id' => $subject->id, 'application_reference' => $app->reference],
            );
            $runId = UuidV7::generate();
            DB::table('screening_runs')->insert([
                'id' => $runId, 'tenant_id' => $this->tenant->requireId(), 'party_id' => $subject->id, 'application_id' => $app->id,
                'trigger' => $command->trigger, 'subject_name' => $subject->displayName, 'provider_reference' => $result->providerReference,
                'list_version' => $result->listVersion, 'hit_count' => count($result->hits), 'screened_by' => $context->principal->id,
                'screened_at' => $now->format('Y-m-d H:i:s.uP'),
            ]);
            $runs++;
            foreach ($result->hits as $hit) {
                // A hit already dispositioned for this party and list entry is not re-raised (FR-CMP-013 suppression of known false positives).
                $known = ScreeningAlert::query()->where('party_id', $subject->id)->where('entry_id', $hit->entryId)->where('status', AlertStatus::Cleared->value)->exists();
                $alert = new ScreeningAlert;
                $alert->forceFill([
                    'screening_run_id' => $runId, 'party_id' => $subject->id, 'application_id' => $app->id, 'org_unit_id' => $app->orgUnitId,
                    'category' => $hit->category, 'list_name' => $hit->listName, 'list_version' => $result->listVersion, 'entry_id' => $hit->entryId,
                    'matched_name' => $hit->matchedName, 'score' => $hit->score,
                    'status' => $known ? AlertStatus::Cleared->value : AlertStatus::Open->value,
                    'confirmation_note' => $known ? 'Suppressed: previously cleared for this party and list entry.' : null,
                ])->save();
                $hits += $known ? 0 : 1;
                $context->audit(new AuditEntry(action: 'compliance.screening_alert.raised', entityType: 'screening_alert', entityId: $alert->id, after: [
                    'application_id' => $app->id, 'party_id' => $subject->id, 'category' => $hit->category, 'entry_id' => $hit->entryId, 'score' => $hit->score, 'status' => $alert->status,
                ]));
            }
        }
        $context->audit(new AuditEntry(action: $command->action(), entityType: 'application', entityId: $app->id, after: ['trigger' => $command->trigger, 'runs' => $runs, 'open_hits' => $hits]));
        $context->raise(new ApplicationScreened($context->principal->tenantId, $app->id, $hits));

        return ['runs' => $runs, 'hits' => $hits];
    }
}

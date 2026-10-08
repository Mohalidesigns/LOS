<?php

declare(strict_types=1);

namespace Fundly\Modules\Compliance\Application;

use Fundly\Modules\Application\Contracts\ApplicationLifecycle;
use Fundly\Modules\Application\Contracts\ApplicationReader;
use Fundly\Modules\Application\Contracts\CanonicalStatus;
use Fundly\Modules\Application\Contracts\Events\ApplicationStatusChanged;
use Fundly\Modules\Compliance\Contracts\Events\ApplicationScreened;
use Fundly\Modules\Compliance\Contracts\Events\ScreeningAlertResolved;
use Fundly\Modules\Party\Contracts\Events\PartyKycChanged;
use Fundly\Shared\Bus\OutboxIntent;
use Fundly\Shared\Outbox\Outbox;
use Fundly\Shared\Security\Principal;
use Fundly\Shared\Security\SystemIdentity;
use Fundly\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Reacts to other modules' events (D-043): queues intake screening when an
 * application enters KycScreening, and moves it to Documentation once the
 * CDD gate is clear. A failure here never undoes the triggering change; it
 * is logged and the gate is re-evaluated on the next relevant event.
 */
final class KycProgression
{
    public const SCREEN_TOPIC = 'compliance.screen_application';

    public function __construct(
        private readonly KycEvaluator $evaluator,
        private readonly ApplicationReader $applications,
        private readonly ApplicationLifecycle $lifecycle,
        private readonly Outbox $outbox,
        private readonly TenantContext $tenant,
        private readonly LoggerInterface $log,
    ) {}

    public function onStatusChanged(ApplicationStatusChanged $e): void
    {
        if ($e->to !== CanonicalStatus::KycScreening->value) {
            return;
        }
        $this->tenant->run($e->tenantId, function () use ($e): void {
            DB::transaction(fn () => $this->outbox->add(new OutboxIntent(
                topic: self::SCREEN_TOPIC,
                payload: ['application_id' => $e->applicationId, 'trigger' => 'intake'],
                aggregateType: 'application',
                aggregateId: $e->applicationId,
                idempotencyKey: "{$e->tenantId}:screen:{$e->applicationId}:v{$e->version}",
                maxAttempts: 8,
            )));
        });
    }

    public function onScreened(ApplicationScreened $e): void
    {
        $this->progress($e->tenantId, $e->applicationId);
    }

    public function onAlertResolved(ScreeningAlertResolved $e): void
    {
        if ($e->applicationId !== null) {
            $this->progress($e->tenantId, $e->applicationId);
        }
    }

    public function onPartyKycChanged(PartyKycChanged $e): void
    {
        $this->tenant->run($e->tenantId, function () use ($e): void {
            $ids = DB::table('application_applicants as aa')
                ->join('applications as a', 'a.id', '=', 'aa.application_id')
                ->where('a.canonical_status', CanonicalStatus::KycScreening->value)
                ->where(static fn ($q) => $q->where('aa.party_id', $e->partyId)->orWhereIn('aa.party_id', DB::table('party_relationships')->select('party_id')->where('related_party_id', $e->partyId)))
                ->distinct()->pluck('a.id');
            foreach ($ids as $id) {
                $this->progress($e->tenantId, (string) $id);
            }
        });
    }

    public function progress(string $tenantId, string $applicationId): void
    {
        $this->tenant->run($tenantId, function () use ($tenantId, $applicationId): void {
            try {
                $app = $this->applications->find($applicationId);
                if ($app === null || $app->status !== CanonicalStatus::KycScreening) {
                    return;
                }
                $gate = $this->evaluator->evaluate($applicationId);
                if (($gate['outcome'] ?? null) === 'clear') {
                    $this->lifecycle->advance($applicationId, CanonicalStatus::Documentation, 'CDD_COMPLETE_SCREENING_CLEAR', 'Gate '.$gate['gate_version'].', risk '.$gate['risk'], Principal::system($tenantId, SystemIdentity::Workflow));
                }
            } catch (Throwable $ex) {
                $this->log->error('KYC progression failed', ['application_id' => $applicationId, 'error' => $ex->getMessage()]);
            }
        });
    }
}

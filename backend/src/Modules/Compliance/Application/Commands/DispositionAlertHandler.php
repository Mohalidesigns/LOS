<?php

declare(strict_types=1);

namespace Fundly\Modules\Compliance\Application\Commands;

use Fundly\Modules\Application\Contracts\ApplicationReader;
use Fundly\Modules\Compliance\Application\AlertPresenter;
use Fundly\Modules\Compliance\Contracts\Events\ScreeningAlertResolved;
use Fundly\Modules\Compliance\Domain\AlertStatus;
use Fundly\Modules\Compliance\Domain\FourEyes;
use Fundly\Modules\Compliance\Infrastructure\Models\ScreeningAlert;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Clock\Clock;
use Fundly\Shared\Exceptions\NotFound;

final class DispositionAlertHandler implements CommandHandler
{
    public function __construct(private readonly ApplicationReader $applications, private readonly Clock $clock) {}

    /** @return array{data: array<string, mixed>} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof DispositionAlert);
        $alert = ScreeningAlert::query()->lockForUpdate()->find($command->alertId) ?? throw new NotFound('Screening alert not found.');
        $originator = $alert->application_id === null ? null : $this->applications->find($alert->application_id)?->originatorId;
        $status = AlertStatus::from($alert->status);
        $actor = $context->principal->id;
        $before = ['status' => $alert->status];

        if ($command->step === 'propose') {
            FourEyes::assertCanPropose($status, $actor, $originator);
            $alert->forceFill([
                'status' => AlertStatus::PendingConfirmation->value, 'proposed_decision' => $command->decision, 'proposed_reason' => $command->reason,
                'evidence_ref' => $command->evidenceRef, 'proposed_by' => $actor, 'proposed_at' => $this->clock->now(),
            ])->save();
        } else {
            FourEyes::assertCanConfirm($status, $actor, $alert->proposed_by, $originator);
            $final = $alert->proposed_decision === 'true_match' ? AlertStatus::ConfirmedMatch : AlertStatus::Cleared;
            $alert->forceFill(['status' => $final->value, 'confirmed_by' => $actor, 'confirmed_at' => $this->clock->now(), 'confirmation_note' => $command->reason])->save();
            $context->raise(new ScreeningAlertResolved($context->principal->tenantId, $alert->id, $alert->application_id, $final->value));
        }
        $context->audit(new AuditEntry(
            action: $command->action(),
            entityType: 'screening_alert',
            entityId: $alert->id,
            before: $before,
            after: ['status' => $alert->status, 'decision' => $alert->proposed_decision, 'list_version' => $alert->list_version, 'evidence_ref' => $alert->evidence_ref],
            reasonText: $command->reason,
        ));

        return ['data' => AlertPresenter::present($alert->refresh())];
    }
}

<?php

declare(strict_types=1);

namespace Fundly\Modules\Disbursement\Application;

use Fundly\Modules\Access\Contracts\ChangeAction;
use Fundly\Modules\Application\Contracts\ApplicationLifecycle;
use Fundly\Modules\Application\Contracts\CanonicalStatus;
use Fundly\Modules\Conditions\Contracts\ConditionsGate;
use Fundly\Modules\Disbursement\Domain\DisbursementStatus;
use Fundly\Modules\Disbursement\Infrastructure\Models\Disbursement;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\OutboxIntent;
use Fundly\Shared\Bus\Payload;
use Fundly\Shared\Crypto\FieldEncryptor;
use Fundly\Shared\Exceptions\DomainRuleViolation;
use Fundly\Shared\Exceptions\NotFound;
use Fundly\Shared\Security\Principal;
use Fundly\Shared\Security\ResourceRef;

/**
 * Disbursement release is maker-checker (FR-DSB-009, TRD §6.4): the checker
 * holds `disbursement:check`, is not the maker and, by policy, did not vote on
 * the approval; the change-request route requires a fresh step-up. Release
 * re-runs the pre-disbursement check and re-checks approval validity
 * (FR-APV-012, D-053) before the booking saga is queued.
 */
final class ReleaseDisbursementAction implements ChangeAction
{
    public const TYPE = 'disbursement.release';

    public const SAGA_TOPIC = 'disbursement.book';

    public function __construct(
        private readonly DisbursementFile $file,
        private readonly ConditionsGate $conditions,
        private readonly ApplicationLifecycle $lifecycle,
        private readonly FieldEncryptor $crypto,
    ) {}

    public function type(): string
    {
        return self::TYPE;
    }

    public function makerPermission(): string
    {
        return 'disbursement:make';
    }

    public function checkerPermission(): string
    {
        return 'disbursement:check';
    }

    public function entity(array $payload): ResourceRef
    {
        return new ResourceRef('disbursement', Payload::string($payload, 'disbursement_id'));
    }

    public function validate(array $payload, Principal $maker): void
    {
        $d = $this->disbursement($payload);
        if ($d->status !== DisbursementStatus::PendingRelease->value) {
            throw new DomainRuleViolation("This instruction is {$d->status}; only a pending instruction can be released.");
        }
    }

    public function fingerprint(array $payload): string
    {
        $d = $this->disbursement($payload);

        return hash('sha256', implode('|', [$d->id, $d->status, $d->net_amount, $d->destination['check_id'], $d->value_date->toDateString()]));
    }

    public function execute(array $payload, string $changeRequestId, CommandContext $context): array
    {
        $d = $this->disbursement($payload);
        $this->validate($payload, $context->principal);
        $app = $this->file->application($d->application_id);
        if (! in_array($app->status, [CanonicalStatus::ReadyForDisbursement, CanonicalStatus::Disbursing], true)) {
            throw new DomainRuleViolation("The application is {$app->status->value}; it cannot be disbursed.");
        }
        $blockers = [];
        if (($approval = $this->file->approvalBlocker($app->id)) !== null) {
            $blockers[] = $approval;
        }
        $blockers = array_merge($blockers, $this->conditions->revalidate($app->id, $context->principal->tenantId));
        $verified = $this->conditions->verifiedDestination($app->id);
        $ours = $this->crypto->decrypt($d->account_no_enc, 'disbursement.account_no');
        if ($verified !== null && ($verified->accountNo !== $ours || $verified->bankCode !== $d->destination['bank_code'])) {
            $blockers[] = 'The verified destination account changed after this instruction was made; cancel it and raise a new one.';
        }
        if ($blockers !== []) {
            throw new DomainRuleViolation('Release is blocked: '.implode(' ', $blockers), ['blockers' => $blockers]);
        }
        $d->forceFill(['status' => DisbursementStatus::PendingCba->value, 'released_by' => $context->principal->id, 'released_at' => $this->file->now()])->save();
        $context->outbox(new OutboxIntent(
            topic: self::SAGA_TOPIC,
            payload: ['disbursement_id' => $d->id],
            aggregateType: 'disbursement',
            aggregateId: $d->id,
            idempotencyKey: "{$context->principal->tenantId}:dsb-book:{$d->id}:release",
            maxAttempts: 8,
        ));
        $context->audit(new AuditEntry(action: 'disbursement.released', entityType: 'disbursement', entityId: $d->id, after: [
            'application_id' => $app->id, 'net' => ['amount' => $d->net_amount, 'currency' => $d->currency], 'destination' => $d->destination, 'change_request_id' => $changeRequestId,
        ]));
        if ($app->status === CanonicalStatus::ReadyForDisbursement) {
            $this->lifecycle->advance($app->id, CanonicalStatus::Disbursing, 'DISBURSEMENT_RELEASED', "Instruction #{$d->sequence} released; booking with the CBA.", $context->principal);
        }

        return ['disbursement_id' => $d->id, 'status' => $d->status];
    }

    /** By policy the checker did not vote on the approval (FR-DSB-009 "where policy requires"). */
    public function excludedCheckers(array $payload): array
    {
        if (! (bool) config('fundly.disbursement.checker_not_approver', true)) {
            return [];
        }

        return $this->file->approverIds($this->disbursement($payload)->application_id);
    }

    /** @param array<string, mixed> $payload */
    private function disbursement(array $payload): Disbursement
    {
        return Disbursement::query()->find(Payload::optionalString($payload, 'disbursement_id')) ?? throw new NotFound('Disbursement not found.');
    }
}

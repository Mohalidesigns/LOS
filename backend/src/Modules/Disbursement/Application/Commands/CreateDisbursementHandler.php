<?php

declare(strict_types=1);

namespace Fundly\Modules\Disbursement\Application\Commands;

use Fundly\Modules\Access\Contracts\ChangeRequestGateway;
use Fundly\Modules\Application\Contracts\CanonicalStatus;
use Fundly\Modules\Conditions\Contracts\ConditionsGate;
use Fundly\Modules\Disbursement\Application\DisbursementFile;
use Fundly\Modules\Disbursement\Application\DisbursementQueries;
use Fundly\Modules\Disbursement\Application\ReleaseDisbursementAction;
use Fundly\Modules\Disbursement\Contracts\Events\DisbursementAwaitingRelease;
use Fundly\Modules\Disbursement\Domain\DisbursementStatus;
use Fundly\Modules\Disbursement\Domain\FeeSplit;
use Fundly\Modules\Disbursement\Infrastructure\Models\Disbursement;
use Fundly\Modules\Offer\Contracts\OfferRegister;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Crypto\FieldEncryptor;
use Fundly\Shared\Exceptions\DomainRuleViolation;
use Fundly\Shared\Exceptions\ValidationFailed;

final class CreateDisbursementHandler implements CommandHandler
{
    public function __construct(
        private readonly DisbursementFile $file,
        private readonly ConditionsGate $conditions,
        private readonly OfferRegister $offers,
        private readonly ChangeRequestGateway $changeRequests,
        private readonly FieldEncryptor $crypto,
        private readonly DisbursementQueries $queries,
    ) {}

    /** @return array{data: array<string, mixed>} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof CreateDisbursement);
        $app = $this->file->application($command->applicationId);
        $live = Disbursement::query()->where('application_id', $app->id)->whereNotIn('status', [DisbursementStatus::Compensated->value, DisbursementStatus::Cancelled->value])->first();
        if ($live !== null) {
            throw new DomainRuleViolation("Instruction #{$live->sequence} is {$live->status}; there can be only one live instruction (FR-DSB-005).");
        }
        // Disbursing without a live instruction means the previous one was compensated
        if (! in_array($app->status, [CanonicalStatus::ReadyForDisbursement, CanonicalStatus::Disbursing], true)) {
            throw new DomainRuleViolation("A disbursement is made once the application is ready for disbursement; this one is {$app->status->value}.");
        }
        $blockers = $this->conditions->releaseBlockers($app->id);
        if (($approval = $this->file->approvalBlocker($app->id)) !== null) {
            $blockers[] = $approval;
        }
        if ($blockers !== []) {
            throw new DomainRuleViolation('The application is not ready for release.', ['blockers' => $blockers]);
        }
        $offer = $this->offers->accepted($app->id) ?? throw new DomainRuleViolation('No accepted offer is on file.');
        $destination = $this->conditions->verifiedDestination($app->id) ?? throw new DomainRuleViolation('Run the pre-disbursement check to verify the destination account.');
        $today = $this->file->now()->toDateString();
        $valueDate = $command->valueDate ?? $today;
        if ($valueDate < $today) {
            throw ValidationFailed::with(['value_date' => 'The value date cannot be in the past.']);
        }
        $split = FeeSplit::split((string) $offer->principal->amount, $offer->fees);

        $d = new Disbursement;
        $d->forceFill([
            'application_id' => $app->id,
            'sequence' => (int) Disbursement::query()->where('application_id', $app->id)->max('sequence') + 1,
            'status' => DisbursementStatus::PendingRelease->value,
            'offer_id' => $offer->offerId,
            'agreement_id' => $offer->agreementId,
            'principal_amount' => (string) $offer->principal->amount,
            'net_amount' => $split['net'],
            'currency' => $offer->principal->currency->code,
            'fees' => $split['deducted'],
            'value_date' => $valueDate,
            'destination' => ['bank_code' => $destination->bankCode, 'account' => '******'.substr($destination->accountNo, -4), 'account_name' => $destination->accountName, 'check_id' => $destination->checkId],
            'account_no_enc' => $this->crypto->encrypt($destination->accountNo, 'disbursement.account_no'),
            'saga_runs' => 0,
            'made_by' => $context->principal->id,
            'made_at' => $this->file->now(),
        ])->save();
        $cr = $this->changeRequests->submit(ReleaseDisbursementAction::TYPE, ['disbursement_id' => $d->id], "Release disbursement #{$d->sequence} for {$app->reference}", $context);
        $d->forceFill(['change_request_id' => is_string($cr['id'] ?? null) ? $cr['id'] : null])->save();
        $context->audit(new AuditEntry(action: $command->action(), entityType: 'disbursement', entityId: $d->id, after: [
            'application_id' => $app->id, 'principal' => $d->principal_amount, 'net' => $d->net_amount, 'fees' => $d->fees, 'destination' => $d->destination,
            'value_date' => $valueDate, 'change_request_id' => $d->change_request_id,
        ]));

        $context->raise(new DisbursementAwaitingRelease($context->principal->tenantId, $app->id, $d->id, $context->principal->id));

        return ['data' => $this->queries->present($d)];
    }
}

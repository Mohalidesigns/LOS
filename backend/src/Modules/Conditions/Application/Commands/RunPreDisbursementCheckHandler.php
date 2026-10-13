<?php

declare(strict_types=1);

namespace Fundly\Modules\Conditions\Application\Commands;

use DateInterval;
use Fundly\Integration\Ports\CoreBanking\CoreBankingPort;
use Fundly\Integration\Ports\CoreBanking\Dto\NameEnquiryResult;
use Fundly\Integration\Runtime\Errors\BusinessRejectionError;
use Fundly\Integration\Runtime\Errors\IntegrationException;
use Fundly\Integration\Runtime\IntegrationGateway;
use Fundly\Modules\Application\Contracts\ApplicationSummary;
use Fundly\Modules\Application\Contracts\CanonicalStatus;
use Fundly\Modules\Approval\Contracts\ApprovalRegister;
use Fundly\Modules\Compliance\Contracts\ComplianceGate;
use Fundly\Modules\Conditions\Application\ConditionFile;
use Fundly\Modules\Conditions\Application\ConditionQueries;
use Fundly\Modules\Conditions\Domain\CheckItem;
use Fundly\Modules\Conditions\Domain\NameMatch;
use Fundly\Modules\Conditions\Infrastructure\Models\PreDisbursementCheck;
use Fundly\Modules\Offer\Contracts\OfferRegister;
use Fundly\Modules\Party\Contracts\PartyDirectory;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Crypto\FieldEncryptor;
use Fundly\Shared\Exceptions\DomainRuleViolation;

/**
 * Runs the whole checklist and records the result, pass or fail: a failed
 * check is evidence too. Nothing here moves the application; disbursement
 * asks ConditionsGate for a passing, in-date check at release.
 */
final class RunPreDisbursementCheckHandler implements CommandHandler
{
    public function __construct(
        private readonly ConditionFile $file,
        private readonly ComplianceGate $compliance,
        private readonly OfferRegister $offers,
        private readonly ApprovalRegister $approvals,
        private readonly PartyDirectory $parties,
        private readonly IntegrationGateway $gateway,
        private readonly FieldEncryptor $crypto,
        private readonly ConditionQueries $queries,
    ) {}

    /** @return array{data: array<string, mixed>} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof RunPreDisbursementCheck);
        $app = $this->file->application($command->applicationId);
        if (! in_array($app->status, [CanonicalStatus::ConditionsPrecedent, CanonicalStatus::ReadyForDisbursement], true)) {
            throw new DomainRuleViolation("The pre-disbursement check runs once the offer is accepted and before release; this application is {$app->status->value}.");
        }
        $now = $this->file->now();
        $items = [];

        // re-screen first, so the CDD gate below sees today's screening
        $screen = $this->compliance->rescreen($app->id, $context->principal->tenantId);
        $items[] = $screen->isClear()
            ? CheckItem::pass('screening_clear', "Re-screened {$screen->runs} part(ies) just now; no open alerts.")
            : CheckItem::fail('screening_clear', "{$screen->openAlerts} screening alert(s) open after re-screening ({$screen->newHits} new). Compliance must clear them (FR-CPR-004).");

        $kyc = $this->compliance->kyc($app->id);
        $items[] = $kyc !== null && $kyc->isClear()
            ? CheckItem::pass('kyc_current', 'CDD gate clear ('.$kyc->risk.' risk).')
            : CheckItem::fail('kyc_current', 'CDD gate not clear: '.implode('; ', $kyc->unmet ?? ['no KYC record']).'.');

        $accepted = $this->offers->accepted($app->id);
        $items[] = $accepted !== null
            ? CheckItem::pass('documentation_executed', 'Executed agreement on file (sha256 '.substr($accepted->agreementSha256, 0, 12).'…).')
            : CheckItem::fail('documentation_executed', 'No executed agreement is on file.');

        $outstanding = $this->file->outstanding($app->id);
        $items[] = $outstanding === []
            ? CheckItem::pass('conditions_precedent', 'Every mandatory condition precedent is met or waived.')
            : CheckItem::fail('conditions_precedent', 'Outstanding: '.implode('; ', array_map(static fn ($c): string => $c->description, $outstanding)).'.');

        $approval = $this->approvals->current($app->id);
        $items[] = match (true) {
            $approval === null => CheckItem::fail('approval_valid', 'No approval decision is on file.'),
            $approval->status === 'approved' && $approval->isValidAt($now->toDateTimeImmutable()) => CheckItem::pass('approval_valid', 'Approved; valid until '.$approval->validUntil?->format('Y-m-d').'.'),
            $approval->status === 'counter_offered' => CheckItem::pass('approval_valid', 'Counter-offer accepted by the customer.'),
            default => CheckItem::fail('approval_valid', "The approval is {$approval->status} or past its validity; re-approval is required (FR-APV-012)."),
        };

        [$accountItem, $accountName, $score] = $this->nameEnquiry($app, $command->bankCode, $command->accountNo);
        $items[] = $accountItem;
        $items[] = CheckItem::notApplicable('security_perfected', 'Collateral perfection is tracked from P1-COL-01; capture it as a condition precedent until then.');
        $items[] = CheckItem::notApplicable('insurance_in_force', 'Insurance is not configured for this product; capture it as a condition precedent if required.');

        $check = new PreDisbursementCheck;
        $check->forceFill([
            'application_id' => $app->id,
            'sequence' => (int) PreDisbursementCheck::query()->where('application_id', $app->id)->max('sequence') + 1,
            'passed' => CheckItem::passed($items),
            'items' => array_map(static fn (CheckItem $i): array => $i->toArray(), $items),
            'bank_code' => $command->bankCode,
            'account_no_enc' => $this->crypto->encrypt($command->accountNo, 'conditions.account_no'),
            'account_no_masked' => '******'.substr($command->accountNo, -4),
            'account_name' => $accountName,
            'name_match_score' => $score,
            'screening' => ['runs' => $screen->runs, 'new_hits' => $screen->newHits, 'open_alerts' => $screen->openAlerts],
            'checked_by' => $context->principal->id,
            'checked_at' => $now,
            'valid_until' => $now->copy()->add(new DateInterval('PT'.max(1, (int) config('fundly.conditions.check_valid_hours', 24)).'H')),
        ])->save();
        $context->audit(new AuditEntry(action: $command->action(), entityType: 'application', entityId: $app->id, after: [
            'check_id' => $check->id, 'passed' => $check->passed, 'failed' => array_values(array_map(static fn (CheckItem $i): string => $i->code, array_filter($items, static fn (CheckItem $i): bool => $i->status === CheckItem::FAIL))),
            'destination' => ['bank_code' => $check->bank_code, 'account' => $check->account_no_masked, 'name_match' => $score],
        ]));

        return ['data' => $this->queries->check($check)];
    }

    /** @return array{0: CheckItem, 1: ?string, 2: ?int} */
    private function nameEnquiry(ApplicationSummary $app, string $bankCode, string $accountNo): array
    {
        $primary = array_search('primary', $app->applicants, true);
        $applicant = is_string($primary) ? $this->parties->find($primary)?->displayName : null;
        try {
            /** @var NameEnquiryResult $result */
            $result = $this->gateway->coreBanking(
                'accounts.nameEnquiry',
                static fn (CoreBankingPort $cba): NameEnquiryResult => $cba->accounts()->nameEnquiry($bankCode, $accountNo),
                ['application_id' => $app->id, 'bank_code' => $bankCode, 'account' => '******'.substr($accountNo, -4)],
                null,
                $app->legalEntityId,
            );
        } catch (BusinessRejectionError $e) {
            return [CheckItem::fail('account_verified', "Name enquiry rejected the account: {$e->getMessage()}"), null, null];
        } catch (IntegrationException $e) {
            return [CheckItem::fail('account_verified', "Name enquiry unavailable ({$e->canonicalCode}); run the check again."), null, null];
        }
        $score = $applicant === null ? 0 : NameMatch::score($applicant, $result->accountName);
        $min = (int) config('fundly.conditions.name_match_min', 80);
        $item = $score >= $min
            ? CheckItem::pass('account_verified', "Account {$bankCode}/******".substr($accountNo, -4)." is held by \"{$result->accountName}\" (name match {$score}%).")
            : CheckItem::fail('account_verified', "Account name \"{$result->accountName}\" does not match the applicant \"{$applicant}\" (score {$score}%, minimum {$min}%).");

        return [$item, $result->accountName, $score];
    }
}

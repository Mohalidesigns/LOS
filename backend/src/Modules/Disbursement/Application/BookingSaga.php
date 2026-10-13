<?php

declare(strict_types=1);

namespace Fundly\Modules\Disbursement\Application;

use Closure;
use Fundly\Integration\Ports\CoreBanking\CoreBankingPort;
use Fundly\Integration\Ports\CoreBanking\Dto\ChargeItem;
use Fundly\Integration\Ports\CoreBanking\Dto\CustomerCreate;
use Fundly\Integration\Ports\CoreBanking\Dto\CustomerSummary;
use Fundly\Integration\Ports\CoreBanking\Dto\Destination;
use Fundly\Integration\Ports\CoreBanking\Dto\DisbursementRequest;
use Fundly\Integration\Ports\CoreBanking\Dto\Instalment;
use Fundly\Integration\Ports\CoreBanking\Dto\LoanAccountCreate;
use Fundly\Integration\Ports\CoreBanking\Dto\LoanAccountRef;
use Fundly\Integration\Ports\CoreBanking\Dto\Posting;
use Fundly\Integration\Ports\CoreBanking\Dto\PostingResult;
use Fundly\Integration\Runtime\Errors\ErrorClass;
use Fundly\Integration\Runtime\Errors\IntegrationException;
use Fundly\Integration\Runtime\Errors\RetryableError;
use Fundly\Integration\Runtime\IntegrationGateway;
use Fundly\Modules\Application\Contracts\ApplicationLifecycle;
use Fundly\Modules\Application\Contracts\ApplicationSummary;
use Fundly\Modules\Application\Contracts\CanonicalStatus;
use Fundly\Modules\Disbursement\Contracts\Events\FacilityBooked;
use Fundly\Modules\Disbursement\Domain\DisbursementStatus;
use Fundly\Modules\Disbursement\Domain\ScheduleVariance;
use Fundly\Modules\Disbursement\Infrastructure\Models\Disbursement;
use Fundly\Modules\Offer\Contracts\AcceptedOffer;
use Fundly\Modules\Offer\Contracts\OfferRegister;
use Fundly\Modules\Party\Contracts\IdentityNumberResolver;
use Fundly\Modules\Party\Contracts\PartyDirectory;
use Fundly\Modules\Product\Contracts\ProductCatalogue;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Crypto\FieldEncryptor;
use Fundly\Shared\Money\Money;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * The booking saga (TRD §11, FR-DSB-001/004..007, FR-CBA-009, LOS-FR-313):
 *
 *   ensure customer → create loan account → disburse net → post upfront fees
 *   → fetch the CBA schedule and compare it with the accepted offer → Booked
 *
 * Every step has a stable idempotency key and records its result on the
 * instruction, so a re-run skips what is done and never posts twice. A
 * write that times out is looked up by its reference before anything is
 * resent. A definite rejection of the disbursement after the account was
 * opened is compensated by closing the account; past the disbursement
 * nothing is compensated automatically: the instruction parks for
 * intervention instead.
 */
final class BookingSaga
{
    public const OUTCOMES = ['booked', 'retryable', 'intervention', 'compensated', 'noop'];

    public function __construct(
        private readonly DisbursementFile $file,
        private readonly OfferRegister $offers,
        private readonly PartyDirectory $parties,
        private readonly IdentityNumberResolver $identities,
        private readonly IntegrationGateway $gateway,
        private readonly FieldEncryptor $crypto,
        private readonly ApplicationLifecycle $lifecycle,
        private readonly AdviceGenerator $advice,
        private readonly LoggerInterface $log,
        private readonly ProductCatalogue $products,
    ) {}

    /** @return 'booked'|'retryable'|'intervention'|'compensated'|'noop' */
    public function run(Disbursement $d, CommandContext $context): string
    {
        $before = DisbursementStatus::from($d->status);
        if ($before !== DisbursementStatus::PendingCba && $before !== DisbursementStatus::FailedRetryable) {
            return 'noop';
        }
        $d->forceFill(['status' => DisbursementStatus::PendingCba->value, 'saga_runs' => $d->saga_runs + 1, 'last_error_code' => null, 'last_error_message' => null])->save();
        $app = $this->file->application($d->application_id);
        $tenant = $context->principal->tenantId;
        $key = static fn (string $step): string => "{$tenant}:dsb:{$d->id}:{$step}";
        $resend = $d->saga_runs > 1;

        try {
            // FR-APV-012: the approval must still stand before anything reaches the CBA
            if ($d->loan_account_no === null && ($blocker = $this->file->approvalBlocker($app->id)) !== null) {
                throw new StepFailed('intervention', 'LOS.APPROVAL.LAPSED', $blocker);
            }
            $offer = $this->offers->accepted($app->id) ?? throw new StepFailed('intervention', 'LOS.OFFER.MISSING', 'No accepted offer is on file.');

            if ($d->cba_customer_id === null) {
                $this->step($d, 'ensure_customer', function (array &$detail) use ($d, $app, $key, $resend): void {
                    $d->cba_customer_id = $this->ensureCustomer($app, $key('customer'), $resend, $detail);
                    $detail['cba_customer_id'] = $d->cba_customer_id;
                });
            }
            if ($d->loan_account_no === null) {
                $this->step($d, 'create_loan_account', function (array &$detail) use ($d, $app, $offer, $key, $resend): void {
                    $ref = $this->createAccount($d, $app, $offer, $key('account'), $resend, $detail);
                    $d->loan_account_no = $ref->loanAccountNo;
                    $detail += ['loan_account_no' => $ref->loanAccountNo, 'cba_reference' => $ref->cbaReference];
                });
            }
            if ($d->posting_ref === null) {
                try {
                    $this->step($d, 'disburse', function (array &$detail) use ($d, $app, $key, $resend): void {
                        $result = $this->disburse($d, $app, $key('disburse'), $resend, $detail);
                        $d->posting_ref = $result->postingRef;
                        $detail += ['posting_ref' => $result->postingRef, 'amount' => Money::of($d->net_amount, $d->currency)->toArray()];
                    });
                } catch (StepFailed $f) {
                    if ($f->kind === 'definite') {
                        return $this->compensate($d, $app, $key('close'), $f, $context);
                    }
                    throw $f;
                }
            }
            if ($d->fee_posting_refs === null) {
                $this->step($d, 'post_fees', function (array &$detail) use ($d, $app, $key): void {
                    $d->fee_posting_refs = $this->postFees($d, $app, $key('fees'), $detail);
                }, $d->fees === []);
            }
            if ($d->cba_schedule === null) {
                $this->step($d, 'schedule_check', function (array &$detail) use ($d, $app, $offer): void {
                    /** @var list<Instalment> $instalments */
                    $instalments = $this->gateway->coreBanking('schedule.get', static fn (CoreBankingPort $cba): array => $cba->schedules()->getSchedule((string) $d->loan_account_no), ['loan_account_no' => $d->loan_account_no], null, $app->legalEntityId);
                    $booked = array_map(static fn (Instalment $i): array => [
                        'number' => $i->number, 'due_date' => $i->dueDate, 'principal' => (string) $i->principal->amount->toScale(2),
                        'interest' => (string) $i->interest->amount->toScale(2), 'total' => (string) $i->total->amount->toScale(2), 'closing_balance' => (string) $i->closingBalance->amount->toScale(2),
                    ], $instalments);
                    $variance = ScheduleVariance::compare($offer->schedule, $booked, (string) config('fundly.disbursement.schedule_tolerance', '1.00'));
                    $d->variance = $variance;
                    $detail += ['instalments' => count($booked), 'max_difference' => $variance['max_difference'], 'within_tolerance' => $variance['within_tolerance']];
                    if (! $variance['within_tolerance']) {
                        // the schedule is only kept once it passes, so a retry checks it again
                        throw new StepFailed('intervention', 'LOS.SCHEDULE.VARIANCE', count($variance['mismatches']).' schedule difference(s) beyond the tolerance of '.$variance['tolerance'].' (LOS-FR-313).');
                    }
                    $d->cba_schedule = $booked;
                });
            }
        } catch (StepFailed $f) {
            return $this->park($d, $app, $before, $f, $context);
        }

        return $this->book($d, $app, $offer, $context);
    }

    /**
     * Runs one step: records it in the step log and on the instruction, and
     * turns integration errors into a StepFailed of the right kind.
     *
     * @param  Closure(array<string, mixed>): void  $body
     */
    private function step(Disbursement $d, string $name, Closure $body, bool $skip = false): void
    {
        if ($skip) {
            $d->save();
            $this->file->log($d, $name, 'skipped', ['reason' => 'nothing to do']);

            return;
        }
        $detail = [];
        try {
            $body($detail);
        } catch (StepFailed $f) {
            $d->save();
            $this->file->log($d, $name, 'failed', $detail + ['message' => $f->getMessage()], $f->errorCode);
            throw $f;
        } catch (IntegrationException $e) {
            $kind = match ($e->errorClass()) {
                ErrorClass::Retryable => 'retryable',
                ErrorClass::BusinessRejection => 'definite',
                default => 'intervention',
            };
            $d->save();
            $this->file->log($d, $name, 'failed', $detail + ['message' => $e->getMessage()], $e->canonicalCode);
            throw new StepFailed($kind, $e->canonicalCode, $e->getMessage());
        }
        $d->save();
        $this->file->log($d, $name, 'succeeded', $detail);
    }

    /**
     * Exactly once over an at-least-once channel: on a re-run look first; on a
     * timeout after sending, look up by reference before deciding.
     *
     * @template T
     *
     * @param  Closure(): T  $send
     * @param  Closure(): (T|null)  $lookup
     * @param  array<string, mixed>  $detail
     * @return T
     */
    private function exactlyOnce(Closure $send, Closure $lookup, bool $lookFirst, array &$detail): mixed
    {
        if ($lookFirst && ($found = $lookup()) !== null) {
            $detail['found_by_lookup_before_resend'] = true;

            return $found;
        }
        try {
            return $send();
        } catch (IntegrationException $e) {
            if (! $e->unknownOutcome) {
                throw $e;
            }
            $detail['outcome_unknown'] = $e->canonicalCode;
            $found = $lookup();
            if ($found !== null) {
                $detail['resolved_by_lookup'] = true;

                return $found;
            }
            throw new RetryableError('CBA.TRANSPORT.NOT_APPLIED', 'The CBA timed out and the effect was not applied; it is safe to retry with the same key.');
        }
    }

    /** @param array<string, mixed> $detail */
    private function ensureCustomer(ApplicationSummary $app, string $key, bool $resend, array &$detail): string
    {
        $partyId = (string) array_search('primary', $app->applicants, true);
        $find = fn (): ?string => $this->gateway->coreBanking('customer.findByLosReference', static fn (CoreBankingPort $cba): ?CustomerSummary => $cba->customers()->findCustomerByLosReference($partyId), ['los_party_id' => $partyId], null, $app->legalEntityId)?->cbaCustomerId;
        $existing = $find();
        if ($existing !== null) {
            $detail['existing_customer'] = true;

            return $existing;
        }
        $profile = $this->parties->kycProfile($partyId) ?? throw new StepFailed('intervention', 'LOS.PARTY.MISSING', 'The primary applicant could not be read.');
        $bvn = $this->identities->resolve($partyId, ['bvn'])['value'] ?? null;
        $create = new CustomerCreate(
            losPartyId: $partyId, kind: $profile->type === 'individual' ? 'individual' : 'organisation', name: $profile->displayName,
            bvn: $bvn, rcNumber: $profile->registrationNumber, dateOfBirth: $profile->dateOfBirth, segment: $app->segment,
        );
        $detail['new_to_bank'] = true;

        return $this->exactlyOnce(
            fn (): string => $this->gateway->coreBanking('customer.create', static fn (CoreBankingPort $cba): string => $cba->customers()->createCustomer($create, $key)->cbaCustomerId, ['los_party_id' => $partyId, 'kind' => $create->kind], $key, $app->legalEntityId),
            $find,
            $resend,
            $detail,
        );
    }

    /** @param array<string, mixed> $detail */
    private function createAccount(Disbursement $d, ApplicationSummary $app, AcceptedOffer $offer, string $key, bool $resend, array &$detail): LoanAccountRef
    {
        $request = new LoanAccountCreate(
            losFacilityId: $d->id, cbaCustomerId: (string) $d->cba_customer_id, productCode: $this->productCode($app),
            principal: $offer->principal, ratePercent: $offer->ratePercent, tenorMonths: $offer->tenorMonths, frequency: $offer->repaymentFrequency,
            moratoriumMonths: 0, startDate: $d->value_date->toDateString(), dayCount: '30/360', branchCode: '001',
        );
        $log = ['los_facility_id' => $d->id, 'principal' => $offer->principal->toArray(), 'rate_percent' => $offer->ratePercent, 'tenor_months' => $offer->tenorMonths];

        return $this->exactlyOnce(
            fn (): LoanAccountRef => $this->gateway->coreBanking('loanAccount.create', static fn (CoreBankingPort $cba): LoanAccountRef => $cba->loanAccounts()->createLoanAccount($request, $key), $log, $key, $app->legalEntityId),
            fn (): ?LoanAccountRef => $this->gateway->coreBanking('loanAccount.findByLosReference', static fn (CoreBankingPort $cba): ?LoanAccountRef => $cba->loanAccounts()->findLoanAccountByLosReference($d->id), ['los_facility_id' => $d->id], null, $app->legalEntityId),
            $resend,
            $detail,
        );
    }

    /** @param array<string, mixed> $detail */
    private function disburse(Disbursement $d, ApplicationSummary $app, string $key, bool $resend, array &$detail): PostingResult
    {
        $dest = $d->destination;
        $request = new DisbursementRequest(
            loanAccountNo: (string) $d->loan_account_no,
            amount: Money::of($d->net_amount, $d->currency),
            destination: new Destination('nip', $this->crypto->decrypt($d->account_no_enc, 'disbursement.account_no'), $dest['bank_code'], $dest['account_name']),
            narration: "Loan disbursement {$app->reference}",
            valueDate: $d->value_date->toDateString(),
            losReference: $key,
        );
        $log = ['loan_account_no' => $request->loanAccountNo, 'amount' => $request->amount->toArray(), 'destination' => ['bank_code' => $dest['bank_code'], 'account' => $dest['account']]];

        return $this->exactlyOnce(
            fn (): PostingResult => $this->gateway->coreBanking('postings.disburse', static fn (CoreBankingPort $cba): PostingResult => $cba->postings()->disburse($request, $key), $log, $key, $app->legalEntityId),
            function () use ($key, $app): ?PostingResult {
                $p = $this->gateway->coreBanking('postings.getByReference', static fn (CoreBankingPort $cba): ?Posting => $cba->postings()->getPostingByReference($key), ['reference' => $key], null, $app->legalEntityId);

                return $p === null ? null : new PostingResult($p->postingRef, $p->status);
            },
            $resend,
            $detail,
        );
    }

    /**
     * The provider is idempotent on the key, so a resend after an unknown
     * outcome returns the original postings rather than charging twice.
     *
     * @param  array<string, mixed>  $detail
     * @return list<string>
     */
    private function postFees(Disbursement $d, ApplicationSummary $app, string $key, array &$detail): array
    {
        $items = array_map(fn (array $f): ChargeItem => new ChargeItem($f['code'], Money::of($f['amount'], $d->currency)), $d->fees);
        try {
            $refs = $this->gateway->coreBanking('postings.charges', static fn (CoreBankingPort $cba): array => $cba->postings()->postCharges((string) $d->loan_account_no, $items, $key), ['loan_account_no' => $d->loan_account_no, 'items' => count($items)], $key, $app->legalEntityId);
        } catch (IntegrationException $e) {
            if ($e->unknownOutcome) {
                $detail['outcome_unknown'] = $e->canonicalCode;
                throw new RetryableError('CBA.TRANSPORT.UNKNOWN_OUTCOME', 'Fee posting outcome unknown; it is retried with the same key.');
            }
            throw $e;
        }
        $detail['posting_refs'] = $refs;
        $detail['fees'] = $d->fees;

        return $refs;
    }

    /**
     * FR-CBA-009: the disbursement was definitively refused after the account was opened, so close the account.
     *
     * @return 'compensated'|'retryable'|'intervention'
     */
    private function compensate(Disbursement $d, ApplicationSummary $app, string $key, StepFailed $cause, CommandContext $context): string
    {
        $detail = ['cause' => $cause->errorCode, 'loan_account_no' => $d->loan_account_no];
        try {
            if ($d->loan_account_no !== null) {
                $this->gateway->coreBanking('loanAccount.close', static fn (CoreBankingPort $cba): string => $cba->loanAccounts()->closeAccount((string) $d->loan_account_no, $key), ['loan_account_no' => $d->loan_account_no], $key, $app->legalEntityId);
            }
        } catch (IntegrationException $e) {
            $this->file->log($d, 'compensate', 'failed', $detail + ['message' => $e->getMessage()], $e->canonicalCode);

            return $this->park($d, $app, DisbursementStatus::PendingCba, new StepFailed('intervention', $e->canonicalCode, 'The disbursement was rejected and closing the loan account failed: '.$e->getMessage()), $context);
        }
        $this->file->log($d, 'compensate', 'compensated', $detail);
        $d->forceFill(['status' => DisbursementStatus::Compensated->value, 'last_error_code' => $cause->errorCode, 'last_error_message' => $cause->getMessage().' The loan account was closed; raise a new instruction once the cause is fixed.'])->save();
        $context->audit(new AuditEntry(action: 'disbursement.compensated', entityType: 'disbursement', entityId: $d->id, after: ['status' => $d->status, 'cause' => $cause->errorCode, 'loan_account_no' => $d->loan_account_no]));
        $this->lifecycle->advance($app->id, CanonicalStatus::Disbursing, 'DISBURSEMENT_COMPENSATED', "Disbursement rejected by the CBA ({$cause->errorCode}); loan account closed.", $context->principal);

        return 'compensated';
    }

    /** @return 'retryable'|'intervention' */
    private function park(Disbursement $d, ApplicationSummary $app, DisbursementStatus $before, StepFailed $f, CommandContext $context): string
    {
        $status = $f->kind === 'retryable' ? DisbursementStatus::FailedRetryable : DisbursementStatus::FailedIntervention;
        $d->forceFill(['status' => $status->value, 'last_error_code' => $f->errorCode, 'last_error_message' => $f->getMessage()])->save();
        $context->audit(new AuditEntry(action: 'disbursement.failed', entityType: 'disbursement', entityId: $d->id, after: ['status' => $d->status, 'error_code' => $f->errorCode, 'run' => $d->saga_runs]));
        // one timeline entry per change of sub-state, not per retry
        if ($before !== $status) {
            $this->lifecycle->advance($app->id, CanonicalStatus::Disbursing, $status === DisbursementStatus::FailedRetryable ? 'DISBURSEMENT_FAILED_RETRYABLE' : 'DISBURSEMENT_NEEDS_INTERVENTION', "{$f->errorCode}: {$f->getMessage()}", $context->principal);
        }

        return $f->kind === 'retryable' ? 'retryable' : 'intervention';
    }

    /** @return 'booked' */
    private function book(Disbursement $d, ApplicationSummary $app, AcceptedOffer $offer, CommandContext $context): string
    {
        $d->forceFill(['status' => DisbursementStatus::Booked->value, 'booked_at' => $this->file->now()])->save();
        $this->file->log($d, 'book', 'succeeded', ['loan_account_no' => $d->loan_account_no, 'posting_ref' => $d->posting_ref]);
        $context->audit(new AuditEntry(action: 'disbursement.booked', entityType: 'disbursement', entityId: $d->id, after: [
            'loan_account_no' => $d->loan_account_no, 'posting_ref' => $d->posting_ref, 'net' => Money::of($d->net_amount, $d->currency)->toArray(), 'runs' => $d->saga_runs,
        ]));
        $this->lifecycle->advance($app->id, CanonicalStatus::Booked, 'BOOKED', "Loan account {$d->loan_account_no}; disbursement posting {$d->posting_ref}.", $context->principal);
        $context->raise(new FacilityBooked($context->principal->tenantId, $app->id, $d->id, (string) $d->loan_account_no, $offer->offerId));
        try {
            // FR-DSB-011: advice + final schedule; a rendering failure never undoes a booking
            $doc = $this->advice->generate($d, $app, $offer);
            $d->forceFill(['advice_document_id' => $doc->documentId, 'advice_version_id' => $doc->versionId])->save();
            $this->file->log($d, 'advice', 'succeeded', ['document_id' => $doc->documentId, 'sha256' => $doc->sha256]);
        } catch (Throwable $e) {
            $this->log->warning('Disbursement advice failed', ['disbursement_id' => $d->id, 'error' => $e->getMessage()]);
            $this->file->log($d, 'advice', 'failed', ['message' => $e->getMessage()]);
        }

        return 'booked';
    }

    private function productCode(ApplicationSummary $app): string
    {
        // the CBA product mapping is the product key until per-product CBA codes are configured (P4 adapter mappings)
        return strtoupper(str_replace('-', '_', $this->products->version($app->productVersionId)->key ?? 'LOAN'));
    }
}

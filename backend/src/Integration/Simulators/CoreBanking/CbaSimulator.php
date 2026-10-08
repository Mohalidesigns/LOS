<?php

declare(strict_types=1);

namespace Fundly\Integration\Simulators\CoreBanking;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DateTimeImmutable;
use Fundly\Integration\Ports\CoreBanking\AccountsPort;
use Fundly\Integration\Ports\CoreBanking\CollateralPort;
use Fundly\Integration\Ports\CoreBanking\CoreBankingPort;
use Fundly\Integration\Ports\CoreBanking\CustomerPort;
use Fundly\Integration\Ports\CoreBanking\Dto\Account;
use Fundly\Integration\Ports\CoreBanking\Dto\AccountStatus;
use Fundly\Integration\Ports\CoreBanking\Dto\Balance;
use Fundly\Integration\Ports\CoreBanking\Dto\CollateralRecord;
use Fundly\Integration\Ports\CoreBanking\Dto\Customer;
use Fundly\Integration\Ports\CoreBanking\Dto\CustomerCreate;
use Fundly\Integration\Ports\CoreBanking\Dto\CustomerRef;
use Fundly\Integration\Ports\CoreBanking\Dto\CustomerSearchCriteria;
use Fundly\Integration\Ports\CoreBanking\Dto\CustomerSummary;
use Fundly\Integration\Ports\CoreBanking\Dto\DisbursementRequest;
use Fundly\Integration\Ports\CoreBanking\Dto\ExposureFacility;
use Fundly\Integration\Ports\CoreBanking\Dto\Instalment;
use Fundly\Integration\Ports\CoreBanking\Dto\LoanAccount;
use Fundly\Integration\Ports\CoreBanking\Dto\LoanAccountCreate;
use Fundly\Integration\Ports\CoreBanking\Dto\LoanAccountRef;
use Fundly\Integration\Ports\CoreBanking\Dto\NameEnquiryResult;
use Fundly\Integration\Ports\CoreBanking\Dto\Posting;
use Fundly\Integration\Ports\CoreBanking\Dto\PostingResult;
use Fundly\Integration\Ports\CoreBanking\Dto\ReferenceItem;
use Fundly\Integration\Ports\CoreBanking\Dto\ScheduleRequest;
use Fundly\Integration\Ports\CoreBanking\Dto\StandingInstruction;
use Fundly\Integration\Ports\CoreBanking\ExposurePort;
use Fundly\Integration\Ports\CoreBanking\LoanAccountPort;
use Fundly\Integration\Ports\CoreBanking\MandatesPort;
use Fundly\Integration\Ports\CoreBanking\Operations;
use Fundly\Integration\Ports\CoreBanking\PostingsPort;
use Fundly\Integration\Ports\CoreBanking\ReconciliationPort;
use Fundly\Integration\Ports\CoreBanking\ReferenceDataPort;
use Fundly\Integration\Ports\CoreBanking\SchedulePort;
use Fundly\Integration\Runtime\CapabilityManifest;
use Fundly\Integration\Runtime\Errors\BusinessRejectionError;
use Fundly\Integration\Runtime\Errors\ErrorClass;
use Fundly\Integration\Runtime\Errors\IntegrationException;
use Fundly\Integration\Runtime\Errors\NativeErrorMap;
use Fundly\Integration\Runtime\Errors\NonRetryableError;
use Fundly\Integration\Runtime\Errors\RequiresInterventionError;
use Fundly\Integration\Runtime\Errors\RetryableError;
use Fundly\Integration\Runtime\Resilience\Sleeper;
use Fundly\Shared\Http\RequestContext;
use Fundly\Shared\Money\Money;

/**
 * Deterministic core banking simulator (FR-CBA-015, NFR-018). Implements the
 * canonical CBI v1.0 contract with durable state and the register §3 fault
 * scripts, so retries, idempotency, lookup-before-retry and breaker behaviour
 * can be exercised end to end without a vendor sandbox.
 *
 * Behaviour without faults: state-changing calls are natively idempotent on
 * the idempotency key (a re-send returns the original result).
 */
final class CbaSimulator implements CoreBankingPort, CustomerPort, AccountsPort, ExposurePort, LoanAccountPort, PostingsPort, SchedulePort, CollateralPort, MandatesPort, ReferenceDataPort, ReconciliationPort
{
    public const KEY = 'cba-simulator';

    public const VERSION = '1.0.0';

    public const UNKNOWN_ACCOUNT = '0000000000';

    private readonly NativeErrorMap $errors;

    /** @param array<string, mixed> $config */
    public function __construct(
        private readonly SimulatorStore $store,
        private readonly FaultScript $faults,
        private readonly Sleeper $sleeper,
        private readonly RequestContext $request,
        private readonly array $config = [],
    ) {
        // The simulator speaks "native" codes like a real CBA so the mapping path is exercised.
        $this->errors = new NativeErrorMap('CBA', [
            'SIM-100' => [ErrorClass::Retryable, 'CBA.TRANSPORT.UNAVAILABLE'],
            'SIM-200' => [ErrorClass::NonRetryable, 'CBA.REQUEST.MALFORMED'],
            'SIM-301' => [ErrorClass::RequiresIntervention, 'CBA.POSTING.PERIOD_CLOSED'],
            'SIM-401' => [ErrorClass::BusinessRejection, 'CBA.POSTING.INSUFFICIENT_FUNDS'],
            'SIM-402' => [ErrorClass::BusinessRejection, 'CBA.ACCOUNT.FROZEN'],
            'SIM-403' => [ErrorClass::BusinessRejection, 'CBA.PRODUCT.INACTIVE'],
        ]);
    }

    public static function capabilities(): CapabilityManifest
    {
        $ops = [];
        foreach (array_keys(Operations::all()) as $op) {
            $ops[$op] = ['support' => 'native'];
        }
        $ops['postings.disburse']['idempotency'] = 'native_key';
        foreach (['collateral.register', 'collateral.update', 'collateral.release', 'mandates.createStandingInstruction', 'mandates.cancelStandingInstruction'] as $op) {
            $ops[$op] = ['support' => 'unsupported', 'substitute' => 'manual_task'];
        }

        return new CapabilityManifest(self::KEY, self::VERSION, CoreBankingPort::CONTRACT_VERSION, 'on_prem:simulator', $ops);
    }

    // ---- CoreBankingPort composition -------------------------------------------------------

    public function customers(): CustomerPort
    {
        return $this;
    }

    public function accounts(): AccountsPort
    {
        return $this;
    }

    public function exposure(): ExposurePort
    {
        return $this;
    }

    public function loanAccounts(): LoanAccountPort
    {
        return $this;
    }

    public function postings(): PostingsPort
    {
        return $this;
    }

    public function schedules(): SchedulePort
    {
        return $this;
    }

    public function collateral(): CollateralPort
    {
        return $this;
    }

    public function mandates(): MandatesPort
    {
        return $this;
    }

    public function reference(): ReferenceDataPort
    {
        return $this;
    }

    public function reconciliation(): ReconciliationPort
    {
        return $this;
    }

    public function manifest(): CapabilityManifest
    {
        return self::capabilities();
    }

    // ---- Customer ---------------------------------------------------------------------------

    public function searchCustomers(CustomerSearchCriteria $criteria): array
    {
        $this->inject('customer.search');
        $out = [];
        foreach ($this->store->all('customer') as $c) {
            $match = ($criteria->bvn !== null && ($c['bvn'] ?? null) === $criteria->bvn)
                || ($criteria->nin !== null && ($c['nin'] ?? null) === $criteria->nin)
                || ($criteria->rcNumber !== null && ($c['rc_number'] ?? null) === $criteria->rcNumber)
                || ($criteria->phone !== null && ($c['phone'] ?? null) === $criteria->phone)
                || ($criteria->name !== null && str_contains(mb_strtolower((string) $c['name']), mb_strtolower($criteria->name)));
            if ($match) {
                $out[] = $this->summary($c);
            }
        }

        return $out;
    }

    public function getCustomer(string $cbaCustomerId): Customer
    {
        $this->inject('customer.get');
        $c = $this->store->get('customer', $cbaCustomerId) ?? throw new BusinessRejectionError('CBA.CUSTOMER.NOT_FOUND', 'Customer not found.');

        return new Customer(
            cbaCustomerId: (string) $c['id'],
            kind: (string) $c['kind'],
            name: (string) $c['name'],
            identities: array_filter(['bvn' => $c['bvn'] ?? null, 'nin' => $c['nin'] ?? null, 'rc_number' => $c['rc_number'] ?? null], 'is_string'),
            contacts: array_filter(['phone' => $c['phone'] ?? null, 'email' => $c['email'] ?? null], 'is_string'),
            segment: is_string($c['segment'] ?? null) ? $c['segment'] : null,
            kycTier: 'tier_3',
            losPartyId: is_string($c['los_party_id'] ?? null) ? $c['los_party_id'] : null,
        );
    }

    public function createCustomer(CustomerCreate $customer, string $idempotencyKey): CustomerRef
    {
        $id = $this->mutate('customer.create', $idempotencyKey, function () use ($customer): array {
            $existing = $this->store->get('customer_by_los', $customer->losPartyId);
            if ($existing !== null) {
                throw new BusinessRejectionError('CBA.CUSTOMER.DUPLICATE', 'A customer already exists for this LOS party.');
            }
            $id = sprintf('SIMC%08d', $this->store->increment('sequence', 'customer'));
            $this->store->putIfAbsent('customer', $id, [
                'id' => $id, 'kind' => $customer->kind, 'name' => $customer->name, 'bvn' => $customer->bvn, 'nin' => $customer->nin,
                'rc_number' => $customer->rcNumber, 'phone' => $customer->phone, 'email' => $customer->email,
                'segment' => $customer->segment, 'los_party_id' => $customer->losPartyId,
            ]);
            $this->store->putIfAbsent('customer_by_los', $customer->losPartyId, ['id' => $id]);

            return ['id' => $id];
        });

        return new CustomerRef((string) $id['id']);
    }

    public function updateCustomer(string $cbaCustomerId, array $changes, string $idempotencyKey): string
    {
        $r = $this->mutate('customer.update', $idempotencyKey, function () use ($cbaCustomerId): array {
            if ($this->store->get('customer', $cbaCustomerId) === null) {
                throw new BusinessRejectionError('CBA.CUSTOMER.NOT_FOUND', 'Customer not found.');
            }

            return ['version' => (string) $this->store->increment('customer_version', $cbaCustomerId)];
        });

        return (string) $r['version'];
    }

    public function getRelationships(string $cbaCustomerId): array
    {
        $this->inject('customer.relationships');

        return [];
    }

    public function findCustomerByLosReference(string $losPartyId): ?CustomerSummary
    {
        $this->inject('customer.findByLosReference');
        $ref = $this->store->get('customer_by_los', $losPartyId);
        if ($ref === null) {
            return null;
        }
        $c = $this->store->get('customer', (string) $ref['id']);

        return $c === null ? null : $this->summary($c);
    }

    // ---- Accounts ---------------------------------------------------------------------------

    public function listAccounts(string $cbaCustomerId): array
    {
        $this->inject('accounts.list');
        $out = [];
        foreach ($this->store->all('loan_account') as $a) {
            if (($a['customer_id'] ?? null) === $cbaCustomerId) {
                $out[] = new Account((string) $a['account_no'], (string) $a['currency'], (string) $a['status'], (string) $a['product_code']);
            }
        }

        return $out;
    }

    public function getBalance(string $accountNo): Balance
    {
        $this->inject('accounts.balance');
        $this->assertKnownAccount($accountNo);
        $amount = Money::of('250000.00', 'NGN');

        return new Balance($amount, $amount, '2026-01-01T00:00:00Z');
    }

    public function verifyAccountStatus(string $accountNo): AccountStatus
    {
        $this->inject('accounts.status');
        $this->assertKnownAccount($accountNo);

        return new AccountStatus('active', $this->accountName($accountNo));
    }

    public function nameEnquiry(string $bankCode, string $accountNo): NameEnquiryResult
    {
        $this->inject('accounts.nameEnquiry');
        if (preg_match('/^\d{10}$/', $accountNo) !== 1) {
            throw new NonRetryableError('CBA.REQUEST.MALFORMED', 'NUBAN account numbers have 10 digits.');
        }
        $this->assertKnownAccount($accountNo);

        return new NameEnquiryResult($this->accountName($accountNo), 100);
    }

    // ---- Exposure ---------------------------------------------------------------------------

    public function getExposure(string $cbaCustomerId, array $connectedCustomerIds = []): array
    {
        $this->inject('exposure.get');
        $ids = array_merge([$cbaCustomerId], $connectedCustomerIds);
        $out = [];
        foreach ($this->store->all('loan_account') as $a) {
            if (in_array($a['customer_id'] ?? null, $ids, true)) {
                $principal = Money::of((string) $a['principal'], (string) $a['currency']);
                $out[] = new ExposureFacility((string) $a['account_no'], (string) $a['product_code'], $principal, $principal, Money::zero((string) $a['currency']), 'performing', 0);
            }
        }

        return $out;
    }

    // ---- Loan accounts ----------------------------------------------------------------------

    public function createLoanAccount(LoanAccountCreate $request, string $idempotencyKey): LoanAccountRef
    {
        $r = $this->mutate('loanAccount.create', $idempotencyKey, function () use ($request): array {
            if ($this->store->get('loan_by_los', $request->losFacilityId) !== null) {
                throw new BusinessRejectionError('CBA.LOAN_ACCOUNT.DUPLICATE', 'A loan account already exists for this LOS facility.');
            }
            $no = sprintf('9%09d', $this->store->increment('sequence', 'loan_account'));
            $ref = 'SIML'.$no;
            $this->store->putIfAbsent('loan_account', $no, [
                'account_no' => $no, 'cba_reference' => $ref, 'customer_id' => $request->cbaCustomerId, 'product_code' => $request->productCode,
                'principal' => $request->principal->toStorage(), 'currency' => $request->principal->currency->code, 'status' => 'active',
                'los_facility_id' => $request->losFacilityId,
            ]);
            $this->store->putIfAbsent('loan_by_los', $request->losFacilityId, ['account_no' => $no, 'cba_reference' => $ref]);

            return ['account_no' => $no, 'cba_reference' => $ref];
        });

        return new LoanAccountRef((string) $r['account_no'], (string) $r['cba_reference']);
    }

    public function getLoanAccount(string $loanAccountNo): LoanAccount
    {
        $this->inject('loanAccount.get');
        $a = $this->store->get('loan_account', $loanAccountNo) ?? throw new BusinessRejectionError('CBA.LOAN_ACCOUNT.NOT_FOUND', 'Loan account not found.');
        $principal = Money::of((string) $a['principal'], (string) $a['currency']);

        return new LoanAccount((string) $a['account_no'], (string) $a['customer_id'], (string) $a['product_code'], $principal, $principal, (string) $a['status'], is_string($a['los_facility_id'] ?? null) ? $a['los_facility_id'] : null);
    }

    public function findLoanAccountByLosReference(string $losFacilityId): ?LoanAccountRef
    {
        $this->inject('loanAccount.findByLosReference');
        $r = $this->store->get('loan_by_los', $losFacilityId);

        return $r === null ? null : new LoanAccountRef((string) $r['account_no'], (string) $r['cba_reference']);
    }

    public function amendTerms(string $loanAccountNo, array $terms, string $idempotencyKey): string
    {
        $r = $this->mutate('loanAccount.amendTerms', $idempotencyKey, fn (): array => ['status' => 'amended']);

        return (string) $r['status'];
    }

    public function closeAccount(string $loanAccountNo, string $idempotencyKey): string
    {
        $r = $this->mutate('loanAccount.close', $idempotencyKey, fn (): array => ['status' => 'closed']);

        return (string) $r['status'];
    }

    // ---- Postings ---------------------------------------------------------------------------

    public function disburse(DisbursementRequest $request, string $idempotencyKey): PostingResult
    {
        $r = $this->mutate('postings.disburse', $idempotencyKey, function () use ($request, $idempotencyKey): array {
            if ($this->store->get('loan_account', $request->loanAccountNo) === null) {
                throw new BusinessRejectionError('CBA.LOAN_ACCOUNT.NOT_FOUND', 'Loan account not found.');
            }
            if ($request->amount->isZero() || $request->amount->isNegative()) {
                throw new NonRetryableError('CBA.REQUEST.MALFORMED', 'Disbursement amount must be positive.');
            }
            $ref = sprintf('SIMP%010d', $this->store->increment('sequence', 'posting'));
            $posting = [
                'posting_ref' => $ref, 'reference' => $request->losReference, 'idempotency_key' => $idempotencyKey,
                'account_no' => $request->loanAccountNo, 'amount' => $request->amount->toStorage(), 'currency' => $request->amount->currency->code,
                'status' => 'posted', 'value_date' => $request->valueDate,
            ];
            $this->store->putIfAbsent('posting', $ref, $posting);
            // The LOS reference is written to a CBA-visible field, so it can be looked up (register §2.3).
            $this->store->putIfAbsent('posting_by_reference', $request->losReference, ['posting_ref' => $ref]);
            $this->store->putIfAbsent('posting_by_reference', $idempotencyKey, ['posting_ref' => $ref]);

            return ['posting_ref' => $ref, 'status' => 'posted'];
        });

        return new PostingResult((string) $r['posting_ref'], (string) $r['status']);
    }

    public function postCharges(string $loanAccountNo, array $items, string $idempotencyKey): array
    {
        $r = $this->mutate('postings.charges', $idempotencyKey, function () use ($items): array {
            $refs = [];
            foreach ($items as $_) {
                $refs[] = sprintf('SIMP%010d', $this->store->increment('sequence', 'posting'));
            }

            return ['refs' => $refs];
        });

        return array_values(array_map('strval', (array) $r['refs']));
    }

    public function reversePosting(string $postingRef, string $reason, string $idempotencyKey): string
    {
        $r = $this->mutate('postings.reverse', $idempotencyKey, function () use ($postingRef): array {
            if ($this->store->get('posting', $postingRef) === null) {
                throw new BusinessRejectionError('CBA.POSTING.NOT_FOUND', 'Posting not found.');
            }

            return ['ref' => sprintf('SIMR%010d', $this->store->increment('sequence', 'reversal'))];
        });

        return (string) $r['ref'];
    }

    public function getPostingByReference(string $reference): ?Posting
    {
        $this->inject('postings.getByReference');
        $idx = $this->store->get('posting_by_reference', $reference);
        if ($idx === null) {
            return null;
        }
        $p = $this->store->get('posting', (string) $idx['posting_ref']);

        return $p === null ? null : $this->posting($p);
    }

    // ---- Schedule ---------------------------------------------------------------------------

    /**
     * Annuity schedule, monthly, with an interest-only moratorium, using
     * brick/math decimals and HALF_EVEN rounding to kobo (no floats).
     */
    public function generateSchedule(ScheduleRequest $request): array
    {
        $this->inject('schedule.generate');
        if ($request->tenorMonths < 1 || $request->moratoriumMonths < 0 || $request->moratoriumMonths >= $request->tenorMonths) {
            throw new NonRetryableError('CBA.SCHEDULE.INVALID_TERMS', 'Tenor must exceed the moratorium.');
        }
        $currency = $request->principal->currency;
        $minor = $currency->minorUnits();
        $r = BigDecimal::of($request->ratePercent)->dividedBy(1200, 20, RoundingMode::HalfEven);
        $balance = $request->principal->amount->toScale($minor, RoundingMode::HalfEven);
        $start = new DateTimeImmutable($request->startDate);
        $amortising = $request->tenorMonths - $request->moratoriumMonths;

        if ($r->isZero()) {
            $payment = $balance->dividedBy($amortising, $minor, RoundingMode::HalfEven);
        } else {
            $growth = BigDecimal::one();
            for ($i = 0; $i < $amortising; $i++) {
                $growth = $growth->multipliedBy($r->plus(1))->toScale(20, RoundingMode::HalfEven);
            }
            $payment = $balance->multipliedBy($r)->multipliedBy($growth)
                ->dividedBy($growth->minus(1), $minor, RoundingMode::HalfEven);
        }

        $out = [];
        for ($n = 1; $n <= $request->tenorMonths; $n++) {
            $interest = $balance->multipliedBy($r)->toScale($minor, RoundingMode::HalfEven);
            if ($n <= $request->moratoriumMonths) {
                $principal = BigDecimal::zero()->toScale($minor);
            } elseif ($n === $request->tenorMonths) {
                $principal = $balance;
            } else {
                $principal = $payment->minus($interest)->toScale($minor);
            }
            $balance = $balance->minus($principal);
            $out[] = new Instalment(
                $n,
                $start->modify('+'.$n.' month')->format('Y-m-d'),
                Money::of($principal, $currency),
                Money::of($interest, $currency),
                Money::of($principal->plus($interest), $currency),
                Money::of($balance, $currency),
            );
        }

        return $out;
    }

    public function getSchedule(string $loanAccountNo): array
    {
        $this->inject('schedule.get');
        $a = $this->store->get('loan_account', $loanAccountNo) ?? throw new BusinessRejectionError('CBA.LOAN_ACCOUNT.NOT_FOUND', 'Loan account not found.');

        return $this->generateSchedule(new ScheduleRequest((string) $a['product_code'], Money::of((string) $a['principal'], (string) $a['currency']), '24', 12, 'monthly', 0, '2026-01-01', '30/360'));
    }

    // ---- Unsupported in the simulator manifest (routed to manual_task by the runtime) ------

    public function registerCollateral(CollateralRecord $record, string $idempotencyKey): string
    {
        throw $this->unsupported('collateral.register');
    }

    public function updateCollateral(string $cbaCollateralId, CollateralRecord $record, string $idempotencyKey): string
    {
        throw $this->unsupported('collateral.update');
    }

    public function releaseCollateral(string $cbaCollateralId, string $idempotencyKey): string
    {
        throw $this->unsupported('collateral.release');
    }

    public function createStandingInstruction(StandingInstruction $instruction, string $idempotencyKey): string
    {
        throw $this->unsupported('mandates.createStandingInstruction');
    }

    public function cancelStandingInstruction(string $siReference, string $idempotencyKey): string
    {
        throw $this->unsupported('mandates.cancelStandingInstruction');
    }

    // ---- Reference data / reconciliation ------------------------------------------------

    public function getProducts(): array
    {
        $this->inject('reference.products');

        return [new ReferenceItem('SIM-TL-01', 'Simulated term loan'), new ReferenceItem('SIM-OD-01', 'Simulated overdraft')];
    }

    public function getBranches(): array
    {
        $this->inject('reference.branches');

        return [new ReferenceItem('001', 'Head Office'), new ReferenceItem('002', 'Ikeja Branch')];
    }

    public function getGlCodes(): array
    {
        $this->inject('reference.glCodes');

        return [new ReferenceItem('10101', 'Loans and advances'), new ReferenceItem('40101', 'Interest income')];
    }

    public function getCurrencies(): array
    {
        $this->inject('reference.currencies');

        return [new ReferenceItem('NGN', 'Naira'), new ReferenceItem('USD', 'US dollar')];
    }

    public function getRates(): array
    {
        $this->inject('reference.rates');

        return ['NGN' => '1', 'USD' => '1550.0000'];
    }

    public function getPostings(string $from, string $to): array
    {
        $this->inject('reconciliation.postings');

        return array_values(array_map(fn (array $p): Posting => $this->posting($p), array_filter(
            $this->store->all('posting'),
            static fn (array $p): bool => (string) $p['value_date'] >= $from && (string) $p['value_date'] <= $to,
        )));
    }

    public function getAccountStatement(string $accountNo, string $from, string $to): array
    {
        $this->inject('reconciliation.statement');

        return [];
    }

    // ---- Fault injection ---------------------------------------------------------------------

    /**
     * Runs a state-changing operation with native idempotency on the key and
     * the fault script applied around the effect.
     *
     * @param  callable(): array<string, mixed>  $effect
     * @return array<string, mixed>
     */
    private function mutate(string $operation, string $idempotencyKey, callable $effect): array
    {
        $rules = $this->activeRules($operation);
        $previous = $this->store->get('idempotency', $operation.'|'.$idempotencyKey);
        if ($previous !== null) {
            if (isset($rules['duplicate'])) {
                throw new BusinessRejectionError('CBA.REQUEST.DUPLICATE', 'Duplicate submission: this idempotency key was already processed.');
            }

            return $previous;
        }

        $this->applyPreEffectFaults($operation, $rules, true);

        $result = $effect();
        $this->store->putIfAbsent('idempotency', $operation.'|'.$idempotencyKey, $result);

        if (isset($rules['timeout_then_success'])) {
            throw new RequiresInterventionError('CBA.TRANSPORT.TIMEOUT_UNKNOWN_OUTCOME', 'Timed out after the request was sent; the outcome is unknown.', true);
        }

        return $result;
    }

    /** Read-only operations: only pre-effect faults apply. */
    private function inject(string $operation): void
    {
        $this->applyPreEffectFaults($operation, $this->activeRules($operation), false);
    }

    /** @param array<string, array<string, mixed>> $rules */
    private function applyPreEffectFaults(string $operation, array $rules, bool $stateChanging): void
    {
        $policy = Operations::policy($operation);
        if (isset($rules['latency'])) {
            $ms = (int) ($rules['latency']['ms'] ?? 0);
            $this->sleeper->sleepMs($ms);
            if ($ms > $policy->timeoutMs) {
                $rules['timeout'] = [];
            }
        }
        if (isset($rules['timeout'])) {
            if ($stateChanging) {
                // From the caller's side a timeout on a write is always an unknown outcome.
                throw new RequiresInterventionError('CBA.TRANSPORT.TIMEOUT_UNKNOWN_OUTCOME', 'No response within the timeout.', true);
            }
            throw new RetryableError('CBA.TRANSPORT.TIMEOUT', 'No response within the timeout.');
        }
        if (isset($rules['reject'])) {
            $code = (string) $rules['reject']['code'];
            if (preg_match(IntegrationException::CODE_PATTERN, $code) === 1) {
                throw new BusinessRejectionError($code, 'Rejected by the core banking system.');
            }
            throw $this->errors->toException($code, 'Simulated native error');
        }
        if (isset($rules['error_rate'])) {
            $seed = (int) ($rules['error_rate']['seed'] ?? 7);
            $n = $this->store->increment('fault_calls', 'error_rate|'.$operation);
            $roll = hexdec(substr(hash('sha256', $seed.'|'.$operation.'|'.$n), 0, 6)) % 100;
            if ($roll < (int) $rules['error_rate']['percent']) {
                throw $this->errors->toException('SIM-100', 'Simulated transient failure');
            }
        }
        if (isset($rules['partial']) && $stateChanging) {
            $n = $this->store->increment('saga_steps', $this->request->correlationId());
            if ($n === (int) $rules['partial']['step']) {
                throw new RetryableError('CBA.TRANSPORT.UNAVAILABLE', "Simulated failure at saga step {$n}.");
            }
        }
    }

    /** @return array<string, array<string, mixed>> fault name => rule, honouring `times` */
    private function activeRules(string $operation): array
    {
        $active = [];
        foreach ($this->faults->rulesFor($operation) as ['index' => $i, 'rule' => $rule]) {
            if (isset($rule['times'])) {
                $used = $this->store->increment('fault_uses', $i.'|'.$operation);
                if ($used > $rule['times']) {
                    continue;
                }
            }
            $active[$rule['fault']] = $rule;
        }

        return $active;
    }

    // ---- helpers -----------------------------------------------------------------------------

    private function unsupported(string $operation): IntegrationException
    {
        return new RequiresInterventionError('CBA.CAPABILITY.UNSUPPORTED', "The simulator does not support {$operation}.");
    }

    private function assertKnownAccount(string $accountNo): void
    {
        if ($accountNo === self::UNKNOWN_ACCOUNT) {
            throw new BusinessRejectionError('CBA.ACCOUNT.NOT_FOUND', 'Account not found.');
        }
    }

    private function accountName(string $accountNo): string
    {
        $names = $this->config['account_names'] ?? [];

        return is_array($names) && isset($names[$accountNo]) && is_string($names[$accountNo]) ? $names[$accountNo] : 'SIMULATED HOLDER '.substr($accountNo, -4);
    }

    /** @param array<string, mixed> $c */
    private function summary(array $c): CustomerSummary
    {
        return new CustomerSummary((string) $c['id'], (string) $c['name'], (string) $c['kind'], is_string($c['segment'] ?? null) ? $c['segment'] : null, is_string($c['los_party_id'] ?? null) ? $c['los_party_id'] : null);
    }

    /** @param array<string, mixed> $p */
    private function posting(array $p): Posting
    {
        return new Posting((string) $p['posting_ref'], (string) $p['reference'], (string) $p['account_no'], Money::of((string) $p['amount'], (string) $p['currency']), (string) $p['status'], (string) $p['value_date']);
    }
}

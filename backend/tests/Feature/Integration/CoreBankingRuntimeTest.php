<?php

declare(strict_types=1);

use Fundly\Integration\Ports\CoreBanking\CoreBankingPort;
use Fundly\Integration\Ports\CoreBanking\Dto\CollateralRecord;
use Fundly\Integration\Ports\CoreBanking\Dto\CustomerCreate;
use Fundly\Integration\Ports\CoreBanking\Dto\CustomerSearchCriteria;
use Fundly\Integration\Ports\CoreBanking\Dto\Destination;
use Fundly\Integration\Ports\CoreBanking\Dto\DisbursementRequest;
use Fundly\Integration\Ports\CoreBanking\Dto\LoanAccountCreate;
use Fundly\Integration\Ports\CoreBanking\Dto\ScheduleRequest;
use Fundly\Integration\Runtime\Errors\BusinessRejectionError;
use Fundly\Integration\Runtime\Errors\NonRetryableError;
use Fundly\Integration\Runtime\Errors\RequiresInterventionError;
use Fundly\Integration\Runtime\Errors\RetryableError;
use Fundly\Integration\Runtime\IntegrationGateway;
use Fundly\Integration\Runtime\Models\AdapterBinding;
use Fundly\Integration\Runtime\OperationPolicy;
use Fundly\Integration\Runtime\Resilience\BreakerState;
use Fundly\Integration\Runtime\Resilience\CircuitBreaker;
use Fundly\Integration\Runtime\Resilience\RandomSource;
use Fundly\Integration\Runtime\Resilience\RecordingSleeper;
use Fundly\Integration\Runtime\Resilience\SeededRandom;
use Fundly\Integration\Runtime\Resilience\Sleeper;
use Fundly\Shared\Http\RequestContext;
use Fundly\Shared\Money\Money;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->tenant = $this->provisionTenant();
    $this->sleeper = new RecordingSleeper;
    $this->app->instance(Sleeper::class, $this->sleeper);
    $this->app->instance(RandomSource::class, new SeededRandom(1234));
    config(['fundly.integration.breaker.failure_threshold' => 3, 'fundly.integration.breaker.open_seconds' => 30]);
    $this->binding = AdapterBinding::query()->create([
        'port' => 'core_banking', 'adapter_key' => 'cba-simulator', 'adapter_version' => '1.0.0',
        'config' => ['account_names' => ['0123456789' => 'ADA OBI']], 'processing_location' => 'on_prem:simulator', 'status' => 'active',
    ]);
});

function faults(array $rules): void
{
    AdapterBinding::query()->whereKey(test()->binding->id)->update(['fault_script' => json_encode(['rules' => $rules])]);
}

function cba(): IntegrationGateway
{
    app()->forgetScopedInstances();
    test()->useTenant(test()->tenant);

    return app(IntegrationGateway::class);
}

function newCustomer(string $los = 'party-1'): CustomerCreate
{
    return new CustomerCreate($los, 'individual', 'Ada Obi', bvn: '22345678991', phone: '+2348031234567', segment: 'retail');
}

function loanRequest(string $facility = 'fac-1', string $customer = 'SIMC00000001'): LoanAccountCreate
{
    return new LoanAccountCreate($facility, $customer, 'SIM-TL-01', Money::of('5000000', 'NGN'), '24', 12, 'monthly', 0, '2026-11-01', '30/360', '001');
}

it('serves customer search/get/create, exposure, loan account, disbursement, posting lookup, name enquiry and schedule through the canonical port', function () {
    $g = cba();
    $ref = $g->coreBanking('customer.create', fn (CoreBankingPort $c) => $c->customers()->createCustomer(newCustomer(), 'k-cust-1'), ['los_party_id' => 'party-1'], 'k-cust-1');
    expect($ref->cbaCustomerId)->toBe('SIMC00000001');
    expect($g->coreBanking('customer.search', fn (CoreBankingPort $c) => $c->customers()->searchCustomers(new CustomerSearchCriteria(bvn: '22345678991'))))->toHaveCount(1);
    expect($g->coreBanking('customer.get', fn (CoreBankingPort $c) => $c->customers()->getCustomer($ref->cbaCustomerId))->name)->toBe('Ada Obi');

    $acct = $g->coreBanking('loanAccount.create', fn (CoreBankingPort $c) => $c->loanAccounts()->createLoanAccount(loanRequest(), 'k-loan-1'), [], 'k-loan-1');
    $exposure = $g->coreBanking('exposure.get', fn (CoreBankingPort $c) => $c->exposure()->getExposure($ref->cbaCustomerId));
    expect($exposure)->toHaveCount(1)->and($exposure[0]->outstanding->toStorage())->toBe('5000000.0000');

    $post = $g->coreBanking('postings.disburse', fn (CoreBankingPort $c) => $c->postings()->disburse(new DisbursementRequest($acct->loanAccountNo, Money::of('5000000', 'NGN'), new Destination('internal', '0123456789'), 'Disbursement', '2026-11-01', 'k-disb-1'), 'k-disb-1'), [], 'k-disb-1');
    expect($post->status)->toBe('posted');
    $found = $g->coreBanking('postings.getByReference', fn (CoreBankingPort $c) => $c->postings()->getPostingByReference('k-disb-1'));
    expect($found?->postingRef)->toBe($post->postingRef)->and($found?->amount->toStorage())->toBe('5000000.0000');

    expect($g->coreBanking('accounts.nameEnquiry', fn (CoreBankingPort $c) => $c->accounts()->nameEnquiry('058', '0123456789'))->accountName)->toBe('ADA OBI');

    $schedule = $g->coreBanking('schedule.generate', fn (CoreBankingPort $c) => $c->schedules()->generateSchedule(new ScheduleRequest('SIM-TL-01', Money::of('1200000', 'NGN'), '24', 12, 'monthly', 2, '2026-11-01', '30/360')));
    expect($schedule)->toHaveCount(12)
        ->and($schedule[0]->principal->isZero())->toBeTrue()                 // moratorium: interest only
        ->and($schedule[0]->interest->toStorage())->toBe('24000.0000')        // 1.2m × 2%
        ->and($schedule[11]->closingBalance->isZero())->toBeTrue();
    $principalSum = array_reduce($schedule, fn (Money $s, $i) => $s->plus($i->principal), Money::zero('NGN'));
    expect($principalSum->toStorage())->toBe('1200000.0000');
})->group('FR-CBA-001', 'FR-CBA-020');

it('is natively idempotent on the key: a re-send returns the original result without a second effect', function () {
    $g = cba();
    $a = $g->coreBanking('customer.create', fn (CoreBankingPort $c) => $c->customers()->createCustomer(newCustomer(), 'k1'), [], 'k1');
    $b = $g->coreBanking('customer.create', fn (CoreBankingPort $c) => $c->customers()->createCustomer(newCustomer(), 'k1'), [], 'k1');
    expect($a->cbaCustomerId)->toBe($b->cbaCustomerId)->and(DB::table('cba_simulator_records')->where('kind', 'customer')->count())->toBe(1);
    // a different key for the same LOS party is a business duplicate
    expect(fn () => $g->coreBanking('customer.create', fn (CoreBankingPort $c) => $c->customers()->createCustomer(newCustomer(), 'k2'), [], 'k2'))
        ->toThrow(BusinessRejectionError::class, 'already exists');
})->group('FR-CBA-007');

it('fault: duplicate returns a duplicate business error on re-send', function () {
    faults([['operation' => 'customer.create', 'fault' => 'duplicate']]);
    cba()->coreBanking('customer.create', fn (CoreBankingPort $c) => $c->customers()->createCustomer(newCustomer(), 'k1'), [], 'k1');
    try {
        cba()->coreBanking('customer.create', fn (CoreBankingPort $c) => $c->customers()->createCustomer(newCustomer(), 'k1'), [], 'k1');
        $this->fail('expected duplicate');
    } catch (BusinessRejectionError $e) {
        expect($e->canonicalCode)->toBe('CBA.REQUEST.DUPLICATE');
    }
})->group('FR-CBA-007', 'FR-CBA-011');

it('fault: latency is applied, and latency beyond the timeout becomes a timeout', function () {
    faults([['operation' => 'customer.get', 'fault' => 'latency', 'ms' => 250]]);
    $g = cba();
    $id = $g->coreBanking('customer.create', fn (CoreBankingPort $c) => $c->customers()->createCustomer(newCustomer(), 'k1'), [], 'k1')->cbaCustomerId;
    $g->coreBanking('customer.get', fn (CoreBankingPort $c) => $c->customers()->getCustomer($id));
    expect($this->sleeper->sleeps)->toContain(250);

    faults([['operation' => 'accounts.balance', 'fault' => 'latency', 'ms' => 6000]]); // > 5 s default timeout
    expect(fn () => cba()->coreBanking('accounts.balance', fn (CoreBankingPort $c) => $c->accounts()->getBalance('0123456789')))
        ->toThrow(RetryableError::class, 'No response within the timeout');
})->group('FR-CBA-010');

it('fault: timeout on reads is retried inline with exponential backoff and full jitter, then surfaces', function () {
    faults([['operation' => 'customer.search', 'fault' => 'timeout', 'times' => 2]]);
    $result = cba()->coreBanking('customer.search', fn (CoreBankingPort $c) => $c->customers()->searchCustomers(new CustomerSearchCriteria(name: 'x')));
    expect($result)->toBe([])
        ->and($this->sleeper->sleeps)->toHaveCount(2)
        ->and($this->sleeper->sleeps[0])->toBeLessThanOrEqual(500)
        ->and($this->sleeper->sleeps[1])->toBeLessThanOrEqual(1000);
    $calls = DB::table('integration_calls')->where('operation', 'customer.search')->orderBy('started_at')->get();
    expect($calls->pluck('outcome')->all())->toBe(['retryable', 'retryable', 'success'])
        ->and($calls->pluck('attempt')->all())->toBe([1, 2, 3])
        ->and($calls->pluck('error_code')->filter()->unique()->values()->all())->toBe(['CBA.TRANSPORT.TIMEOUT']);
})->group('FR-CBA-010', 'FR-CBA-016');

it('never retries a state-changing call inline; a timeout there is an unknown outcome', function () {
    faults([['operation' => 'postings.disburse', 'fault' => 'timeout']]);
    $g = cba();
    $acct = $g->coreBanking('loanAccount.create', fn (CoreBankingPort $c) => $c->loanAccounts()->createLoanAccount(loanRequest(), 'k-l'), [], 'k-l');
    try {
        $g->coreBanking('postings.disburse', fn (CoreBankingPort $c) => $c->postings()->disburse(new DisbursementRequest($acct->loanAccountNo, Money::of('10', 'NGN'), new Destination('internal', '1'), 'n', '2026-11-01', 'k-d'), 'k-d'), [], 'k-d');
        $this->fail('expected timeout');
    } catch (RequiresInterventionError $e) {
        expect($e->unknownOutcome)->toBeTrue()->and($e->canonicalCode)->toBe('CBA.TRANSPORT.TIMEOUT_UNKNOWN_OUTCOME');
    }
    expect(DB::table('integration_calls')->where('operation', 'postings.disburse')->count())->toBe(1)->and($this->sleeper->sleeps)->toBe([]);
})->group('FR-CBA-007', 'FR-CBA-011');

it('fault: reject maps to a business rejection; unmapped native codes become requires_intervention, never success', function () {
    faults([['operation' => 'postings.disburse', 'fault' => 'reject', 'code' => 'SIM-401'], ['operation' => 'loanAccount.get', 'fault' => 'reject', 'code' => 'SIM-777']]);
    $g = cba();
    $acct = $g->coreBanking('loanAccount.create', fn (CoreBankingPort $c) => $c->loanAccounts()->createLoanAccount(loanRequest(), 'k-l'), [], 'k-l');
    expect(fn () => $g->coreBanking('postings.disburse', fn (CoreBankingPort $c) => $c->postings()->disburse(new DisbursementRequest($acct->loanAccountNo, Money::of('10', 'NGN'), new Destination('internal', '1'), 'n', '2026-11-01', 'k'), 'k'), [], 'k'))
        ->toThrow(BusinessRejectionError::class);
    expect(DB::table('integration_calls')->where('operation', 'postings.disburse')->value('error_code'))->toBe('CBA.POSTING.INSUFFICIENT_FUNDS');
    try {
        $g->coreBanking('loanAccount.get', fn (CoreBankingPort $c) => $c->loanAccounts()->getLoanAccount($acct->loanAccountNo));
        $this->fail('expected intervention');
    } catch (RequiresInterventionError $e) {
        expect($e->canonicalCode)->toBe('CBA.UNMAPPED.SIM_777');
    }
})->group('FR-CBA-011');

it('fault: error_rate injects deterministic transient failures and trips the circuit breaker, which alerts and short-circuits', function () {
    faults([['operation' => 'reference.products', 'fault' => 'error_rate', 'percent' => 100]]);
    $key = $this->binding->id.':reference.products';
    // one call = 1 attempt + 2 inline retries; the third consecutive failure opens the circuit
    expect(fn () => cba()->coreBanking('reference.products', fn (CoreBankingPort $c) => $c->reference()->getProducts()))->toThrow(RetryableError::class);
    expect(app(CircuitBreaker::class)->state($key)->state)->toBe(BreakerState::OPEN)
        ->and($this->sleeper->sleeps)->toHaveCount(2);
    expect(DB::table('circuit_events')->where('breaker_key', $key)->where('to_state', 'open')->exists())->toBeTrue()
        ->and(DB::table('audit_events')->where('action', 'integration.circuit.opened')->value('actor_id'))->toBe('system:integration-runtime');

    // open: fails fast without reaching the provider
    $providerCalls = fn () => (int) json_decode((string) DB::table('cba_simulator_records')->where('kind', 'fault_calls')->value('data'), true)['n'];
    $before = $providerCalls();
    expect(fn () => cba()->coreBanking('reference.products', fn (CoreBankingPort $c) => $c->reference()->getProducts()))->toThrow(RetryableError::class, 'Circuit');
    expect($providerCalls())->toBe($before)
        ->and(DB::table('integration_calls')->where('outcome', 'short_circuited')->count())->toBe(1);

    // after the cool-down one probe is allowed; success closes the circuit
    faults([]);
    $this->travel(31)->seconds();
    expect(cba()->coreBanking('reference.products', fn (CoreBankingPort $c) => $c->reference()->getProducts()))->toHaveCount(2);
    expect(app(CircuitBreaker::class)->state($key)->state)->toBe(BreakerState::CLOSED);
    expect(DB::table('circuit_events')->where('breaker_key', $key)->orderBy('occurred_at')->pluck('to_state')->all())->toBe(['open', 'half_open', 'closed']);
})->group('FR-CBA-010');

it('error_rate below 100% is a proportion, reproducible from the seed', function () {
    faults([['operation' => 'reference.branches', 'fault' => 'error_rate', 'percent' => 30, 'seed' => 99]]);
    config(['fundly.integration.breaker.failure_threshold' => 1000]);
    $failures = 0;
    foreach (range(1, 60) as $_) {
        try {
            cba()->call('core_banking', 'reference.branches', new OperationPolicy(false, 1000, 0), fn (CoreBankingPort $c) => $c->reference()->getBranches());
        } catch (RetryableError) {
            $failures++;
        }
    }
    expect($failures)->toBeGreaterThan(8)->toBeLessThan(30);
})->group('FR-CBA-010');

it('fault: partial(step) fails the Nth state-changing step of a saga (correlation)', function () {
    faults([['fault' => 'partial', 'step' => 2]]);
    $g = cba();
    app(RequestContext::class)->setCorrelationId('saga-0001'); // one saga = one correlation id
    $g->coreBanking('customer.create', fn (CoreBankingPort $c) => $c->customers()->createCustomer(newCustomer(), 'k1'), [], 'k1');
    expect(fn () => $g->coreBanking('loanAccount.create', fn (CoreBankingPort $c) => $c->loanAccounts()->createLoanAccount(loanRequest(), 'k2'), [], 'k2'))
        ->toThrow(RetryableError::class, 'saga step 2');
    expect(DB::table('cba_simulator_records')->where('kind', 'customer')->count())->toBe(1)
        ->and(DB::table('cba_simulator_records')->where('kind', 'loan_account')->count())->toBe(0);
})->group('FR-CBA-007');

it('logs every call with PII masked, latency, correlation id and adapter version', function () {
    app(RequestContext::class)->setCorrelationId('corr-int-0001');
    $g = app(IntegrationGateway::class);
    $g->coreBanking('customer.create', fn (CoreBankingPort $c) => $c->customers()->createCustomer(newCustomer(), 'k1'), ['bvn' => '22345678991', 'phone' => '+2348031234567', 'name' => 'Ada'], 'k1');
    $call = DB::table('integration_calls')->where('operation', 'customer.create')->first();
    $req = json_decode($call->request, true);
    expect($req['bvn'])->toBe('2234*****91')->and($req['phone'])->toBe('+234********67')
        ->and($call->correlation_id)->toBe('corr-int-0001')->and($call->adapter_version)->toBe('1.0.0')
        ->and($call->contract_version)->toBe('1.0')->and($call->idempotency_key)->toBe('k1')->and($call->latency_ms)->toBeInt();
})->group('FR-CBA-016', 'FR-CMP-036');

it('routes operations the manifest marks unsupported to their substitute instead of failing silently', function () {
    try {
        cba()->coreBanking('collateral.register', fn (CoreBankingPort $c) => $c->collateral()->registerCollateral(new CollateralRecord('c1', 'SIMC1', 'land', Money::of('1', 'NGN')), 'k'));
        $this->fail('expected substitute routing');
    } catch (RequiresInterventionError $e) {
        expect($e->canonicalCode)->toBe('CBA.CAPABILITY.UNSUPPORTED')->and($e->getMessage())->toContain('substitute: manual_task');
    }
})->group('FR-CBA-001');

it('refuses to bind the simulator in a production installation without an explicit override, and ignores fault scripts there', function () {
    config(['fundly.installation.environment' => 'production']);
    expect(fn () => cba()->coreBanking('reference.products', fn (CoreBankingPort $c) => $c->reference()->getProducts()))
        ->toThrow(NonRetryableError::class, 'production');
    AdapterBinding::query()->whereKey($this->binding->id)->update(['allow_in_production' => true]);
    faults([['operation' => 'reference.products', 'fault' => 'error_rate', 'percent' => 100]]);
    expect(cba()->coreBanking('reference.products', fn (CoreBankingPort $c) => $c->reference()->getProducts()))->toHaveCount(2);
})->group('FR-CBA-020');

it('resolves legal-entity specific bindings before the tenant default, and errors clearly when none exists', function () {
    AdapterBinding::query()->whereKey($this->binding->id)->update(['status' => 'inactive']);
    expect(fn () => cba()->coreBanking('reference.products', fn (CoreBankingPort $c) => $c->reference()->getProducts()))
        ->toThrow(NonRetryableError::class, 'No active adapter binding');
})->group('FR-CBA-020', 'LOS-FR-284');

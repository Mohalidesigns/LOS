<?php

declare(strict_types=1);

use Fundly\Integration\Ports\CoreBanking\Dto\LoanAccountCreate;
use Fundly\Integration\Runtime\Handlers\CreateLoanAccountHandler;
use Fundly\Integration\Runtime\Handlers\DisburseHandler;
use Fundly\Integration\Runtime\IntegrationGateway;
use Fundly\Integration\Runtime\Models\AdapterBinding;
use Fundly\Integration\Runtime\Outbox\DispatchOutboxJob;
use Fundly\Integration\Runtime\Outbox\OutboxDispatcher;
use Fundly\Integration\Runtime\Outbox\OutboxMessage;
use Fundly\Integration\Runtime\Resilience\RandomSource;
use Fundly\Integration\Runtime\Resilience\RecordingSleeper;
use Fundly\Integration\Runtime\Resilience\SeededRandom;
use Fundly\Integration\Runtime\Resilience\Sleeper;
use Fundly\Modules\Access\Domain\Permission;
use Fundly\Modules\Licensing\Contracts\LicenceFailSafe;
use Fundly\Modules\Licensing\Http\Middleware\EnsureJobLicensed;
use Fundly\Shared\Bus\OutboxIntent;
use Fundly\Shared\Money\Money;
use Fundly\Shared\Outbox\Outbox;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->tenant = $this->provisionTenant();
    $this->app->instance(Sleeper::class, new RecordingSleeper);
    $this->app->instance(RandomSource::class, new SeededRandom(7));
    config(['fundly.integration.breaker.failure_threshold' => 100]);
    $this->binding = AdapterBinding::query()->create(['port' => 'core_banking', 'adapter_key' => 'cba-simulator', 'adapter_version' => '1.0.0', 'config' => [], 'processing_location' => 'on_prem:simulator', 'status' => 'active']);
});

function setFaults(array $rules): void
{
    AdapterBinding::query()->whereKey(test()->binding->id)->update(['fault_script' => json_encode(['rules' => $rules])]);
}

function enqueueLoan(string $facility = 'fac-9', int $maxAttempts = 5): string
{
    return app(Outbox::class)->add(new OutboxIntent(CreateLoanAccountHandler::TOPIC, [
        'los_facility_id' => $facility, 'cba_customer_id' => 'SIMC00000001', 'product_code' => 'SIM-TL-01',
        'principal' => ['amount' => '2500000.0000', 'currency' => 'NGN'], 'rate_percent' => '22.5', 'tenor_months' => 24, 'start_date' => '2026-11-01',
    ], 'facility', $facility, test()->tenant->id.':loanAccount.create:'.$facility, $maxAttempts));
}

function dispatchOutbox(): array
{
    app()->forgetScopedInstances();
    $stats = app(OutboxDispatcher::class)->dispatchDue(test()->tenant->id);
    test()->useTenant(test()->tenant);

    return $stats;
}

function message(string $id): object
{
    return DB::table('outbox_messages')->where('id', $id)->first();
}

it('delivers a committed outbox message and records the result', function () {
    $id = enqueueLoan();
    expect(dispatchOutbox()['dispatched'])->toBe(1);
    $m = message($id);
    expect($m->status)->toBe('dispatched')->and($m->attempts)->toBe(1)->and($m->dispatched_at)->not->toBeNull();
    expect(DB::table('audit_events')->where('action', 'integration.outbox.dispatched')->where('entity_id', $id)->value('actor_id'))->toBe('system:outbox');
    expect(dispatchOutbox()['dispatched'])->toBe(0); // nothing delivered twice
})->group('FR-CBA-008', 'FR-AUD-005');

it('books exactly once when the provider times out after applying the effect (timeout_then_success)', function () {
    setFaults([['operation' => 'loanAccount.create', 'fault' => 'timeout_then_success']]);
    $id = enqueueLoan();
    expect(dispatchOutbox()['dispatched'])->toBe(1);
    expect(DB::table('cba_simulator_records')->where('kind', 'loan_account')->count())->toBe(1);
    $calls = DB::table('integration_calls')->orderBy('started_at')->pluck('operation')->all();
    expect($calls)->toBe(['loanAccount.create', 'loanAccount.findByLosReference']);
})->group('FR-CBA-007', 'FR-CBA-008');

it('re-delivers after a crash (lease expiry) and looks up before re-sending, so the effect still happens once', function () {
    setFaults([['operation' => 'loanAccount.create', 'fault' => 'duplicate']]);
    $id = enqueueLoan();
    // a worker claims the message, the provider applies it, then the worker dies before recording the outcome
    DB::table('outbox_messages')->where('id', $id)->update(['attempts' => 1, 'available_at' => now()->addMinutes(5)]);
    app(CreateLoanAccountHandler::class)->handle(new OutboxMessage($id, $this->tenant->id, CreateLoanAccountHandler::TOPIC, json_decode(message($id)->payload, true), message($id)->idempotency_key, 1, null));
    expect(dispatchOutbox()['dispatched'])->toBe(0); // still leased

    $this->travel(6)->minutes();
    expect(dispatchOutbox()['dispatched'])->toBe(1);
    expect(message($id)->attempts)->toBe(2)
        ->and(DB::table('cba_simulator_records')->where('kind', 'loan_account')->count())->toBe(1)
        // the re-delivery looked up and did not re-send (a re-send would have hit the duplicate fault)
        ->and(DB::table('integration_calls')->where('operation', 'loanAccount.create')->count())->toBe(1);
})->group('FR-CBA-007', 'FR-CBA-008');

it('never blind-retries a disbursement: on an unknown outcome it looks the posting up, then retries with the same key only if absent', function () {
    $g = app(IntegrationGateway::class);
    $acct = $g->coreBanking('loanAccount.create', fn ($c) => $c->loanAccounts()->createLoanAccount(new LoanAccountCreate('fac-d', 'SIMC1', 'SIM-TL-01', Money::of('100', 'NGN'), '20', 6, 'monthly', 0, '2026-11-01', '30/360', '001'), 'k-l'), [], 'k-l');
    setFaults([['operation' => 'postings.disburse', 'fault' => 'timeout', 'times' => 1]]);
    $key = $this->tenant->id.':disburse:fac-d:1';
    $id = app(Outbox::class)->add(new OutboxIntent(DisburseHandler::TOPIC, [
        'loan_account_no' => $acct->loanAccountNo, 'amount' => ['amount' => '100.0000', 'currency' => 'NGN'],
        'destination' => ['type' => 'internal', 'account_no' => '0123456789'], 'value_date' => '2026-11-01',
    ], 'facility', 'fac-d', $key));

    expect(dispatchOutbox()['retried'])->toBe(1);
    $m = message($id);
    expect($m->status)->toBe('pending')->and($m->last_error_code)->toBe('CBA.TRANSPORT.NOT_APPLIED')
        ->and(DB::table('cba_simulator_records')->where('kind', 'posting')->count())->toBe(0);

    $this->travel(10)->minutes();
    expect(dispatchOutbox()['dispatched'])->toBe(1);
    expect(DB::table('cba_simulator_records')->where('kind', 'posting')->count())->toBe(1);
    $ops = DB::table('integration_calls')->where('idempotency_key', $key)->orWhere('operation', 'postings.getByReference')->orderBy('started_at')->pluck('operation')->all();
    expect($ops)->toBe(['postings.disburse', 'postings.getByReference', 'postings.getByReference', 'postings.disburse']);
})->group('FR-CBA-007', 'FR-CBA-008', 'FR-CBA-011');

it('retries retryable failures with backoff until max attempts, then parks for intervention', function () {
    setFaults([['operation' => 'loanAccount.create', 'fault' => 'error_rate', 'percent' => 100]]);
    $id = enqueueLoan(maxAttempts: 3);
    expect(dispatchOutbox()['retried'])->toBe(1);
    $first = message($id);
    expect($first->status)->toBe('pending')->and($first->attempts)->toBe(1)->and($first->last_error_class)->toBe('retryable')
        ->and(strtotime($first->available_at))->toBeGreaterThanOrEqual(now()->getTimestamp());
    foreach (range(1, 2) as $_) {
        $this->travel(10)->minutes();
        dispatchOutbox();
    }
    $m = message($id);
    expect($m->status)->toBe('parked')->and($m->attempts)->toBe(3)->and($m->last_error_class)->toBe('requires_intervention');
})->group('FR-CBA-008', 'FR-CBA-010', 'FR-CBA-011');

it('fails non-retryable errors and rejects business rejections without retrying', function () {
    setFaults([['operation' => 'loanAccount.create', 'fault' => 'reject', 'code' => 'SIM-200']]);
    $failed = enqueueLoan('fac-a');
    dispatchOutbox();
    expect(message($failed)->status)->toBe('failed')->and(message($failed)->last_error_code)->toBe('CBA.REQUEST.MALFORMED');

    setFaults([['operation' => 'loanAccount.create', 'fault' => 'reject', 'code' => 'CBA.PRODUCT.INACTIVE']]);
    $rejected = enqueueLoan('fac-b');
    dispatchOutbox();
    expect(message($rejected)->status)->toBe('rejected')->and(message($rejected)->attempts)->toBe(1);
})->group('FR-CBA-011');

it('runs the dispatcher as a queued job for every tenant, as licence fail-safe work', function () {
    $id = enqueueLoan();
    expect(new DispatchOutboxJob)->toBeInstanceOf(LicenceFailSafe::class);
    // even with no valid licence the job must run (D-034)
    DB::table('licences')->update(['status' => 'superseded']);
    $guard = app(EnsureJobLicensed::class);
    $ran = false;
    $guard->handle(new DispatchOutboxJob, function ($job) use (&$ran) {
        app()->call([$job, 'handle']);
        $ran = true;
    });
    $this->useTenant($this->tenant);
    expect($ran)->toBeTrue()->and(message($id)->status)->toBe('dispatched');
})->group('FR-CBA-008', 'LOS-FR-316');

it('exposes adapter bindings with their manifest and the masked call log over the API', function () {
    $this->login($this->tenant->admin());
    $bindings = $this->api('GET', '/api/v1/adapter-bindings')->assertOk()->json('data');
    expect($bindings[0]['adapter_key'])->toBe('cba-simulator');
    $manifest = $this->api('GET', "/api/v1/adapter-bindings/{$this->binding->id}")->assertOk()->assertJsonPath('data.manifest.cbi', '1.0')->json('data.manifest');
    expect($manifest['operations']['collateral.register'])->toBe(['support' => 'unsupported', 'substitute' => 'manual_task'])
        ->and($manifest['operations']['postings.disburse']['support'])->toBe('native');
    enqueueLoan();
    dispatchOutbox();
    $calls = $this->api('GET', '/api/v1/integration-calls?filter[operation]=loanAccount.create')->assertOk()->json('data');
    expect($calls)->toHaveCount(1)->and($calls[0]['outcome'])->toBe('success');

    $nobody = $this->userWith([Permission::AuditRead]);
    $this->login($nobody);
    $this->api('GET', '/api/v1/integration-calls')->assertForbidden();
})->group('FR-CBA-016', 'FR-CBA-001');

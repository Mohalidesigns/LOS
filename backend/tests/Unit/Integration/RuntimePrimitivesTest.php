<?php

declare(strict_types=1);

use Fundly\Integration\Ports\CoreBanking\Operations;
use Fundly\Integration\Runtime\CapabilityManifest;
use Fundly\Integration\Runtime\Errors\BusinessRejectionError;
use Fundly\Integration\Runtime\Errors\ErrorClass;
use Fundly\Integration\Runtime\Errors\NativeErrorMap;
use Fundly\Integration\Runtime\Errors\RequiresInterventionError;
use Fundly\Integration\Runtime\Errors\RetryableError;
use Fundly\Integration\Runtime\LookupBeforeRetry;
use Fundly\Integration\Runtime\OperationSupport;
use Fundly\Integration\Runtime\Resilience\RetryPolicy;
use Fundly\Integration\Runtime\Resilience\SeededRandom;
use Fundly\Integration\Simulators\CoreBanking\CbaSimulator;
use Fundly\Integration\Simulators\CoreBanking\FaultScript;

it('backs off exponentially with full jitter, capped, in integer milliseconds', function () {
    $policy = new RetryPolicy(6, 500, 4000, new SeededRandom(42));
    expect(array_map($policy->ceilingMs(...), [1, 2, 3, 4, 5, 6]))->toBe([500, 1000, 2000, 4000, 4000, 4000]);
    foreach (range(1, 200) as $i) {
        $attempt = ($i % 6) + 1;
        $d = $policy->delayMs($attempt);
        expect($d)->toBeInt()->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual($policy->ceilingMs($attempt));
    }
    // "full" jitter: delays spread across the whole window, not clustered at the cap
    $samples = array_map(fn () => $policy->delayMs(4), range(1, 400));
    expect(min($samples))->toBeLessThan(500)->and(max($samples))->toBeGreaterThan(3500);
    expect($policy->hasAttemptsLeft(5))->toBeTrue()->and($policy->hasAttemptsLeft(6))->toBeFalse();
})->group('FR-CBA-010');

it('maps native codes to the canonical taxonomy and never maps unknown codes to success', function () {
    $map = new NativeErrorMap('CBA', [
        'E01' => [ErrorClass::Retryable, 'CBA.TRANSPORT.UNAVAILABLE'],
        'GL-CLOSED' => [ErrorClass::RequiresIntervention, 'CBA.POSTING.PERIOD_CLOSED'],
        'NSF' => [ErrorClass::BusinessRejection, 'CBA.POSTING.INSUFFICIENT_FUNDS'],
    ]);
    expect($map->toException('E01', 'x'))->toBeInstanceOf(RetryableError::class)
        ->and($map->toException('GL-CLOSED', 'x')->canonicalCode)->toBe('CBA.POSTING.PERIOD_CLOSED')
        ->and($map->toException('NSF', 'x'))->toBeInstanceOf(BusinessRejectionError::class);
    $unknown = $map->toException('ZZ-999', 'weird');
    expect($unknown)->toBeInstanceOf(RequiresInterventionError::class)
        ->and($unknown->errorClass())->toBe(ErrorClass::RequiresIntervention)
        ->and($unknown->canonicalCode)->toBe('CBA.UNMAPPED.ZZ_999');
})->group('FR-CBA-011');

it('rejects non-canonical error codes', function () {
    new RetryableError('timeout', 'x');
})->throws(InvalidArgumentException::class)->group('FR-CBA-011');

it('declares a capability manifest and requires a substitute for unsupported operations', function () {
    $m = CbaSimulator::capabilities();
    expect($m->contractVersion)->toBe('1.0')
        ->and($m->support('postings.disburse'))->toBe(OperationSupport::Native)
        ->and($m->support('collateral.register'))->toBe(OperationSupport::Unsupported)
        ->and($m->substitute('collateral.register'))->toBe('manual_task')
        ->and(array_keys($m->operations))->toEqualCanonicalizing(array_keys(Operations::all()));
    new CapabilityManifest('x', '1', '1.0', 'on_prem', ['postings.disburse' => ['support' => 'unsupported']]);
})->throws(InvalidArgumentException::class)->group('FR-CBA-001', 'FR-CBA-020');

it('catalogues every canonical operation with its write/read policy', function () {
    expect(Operations::policy('postings.disburse')->stateChanging)->toBeTrue()
        ->and(Operations::policy('postings.disburse')->inlineRetries)->toBe(0)
        ->and(Operations::policy('customer.search')->inlineRetries)->toBe(2)
        ->and(Operations::policy('postings.getByReference')->inlineRetries)->toBe(3);
})->group('FR-CBA-001');

it('parses and validates fault scripts', function () {
    $f = FaultScript::fromArray(['rules' => [['operation' => 'postings.disburse', 'fault' => 'timeout_then_success'], ['fault' => 'latency', 'ms' => 50]]]);
    expect($f->rulesFor('postings.disburse'))->toHaveCount(2)->and($f->rulesFor('customer.get'))->toHaveCount(1);
    FaultScript::fromArray([['fault' => 'error_rate', 'percent' => 101]]);
})->throws(InvalidArgumentException::class)->group('FR-CBA-007');

it('looks up before re-sending and converts a not-applied timeout into a safe retry', function () {
    $sent = 0;
    // re-delivery where the effect already exists: no send at all
    $r = LookupBeforeRetry::execute(function () use (&$sent) { $sent++; return 'new'; }, fn () => 'existing', true);
    expect($r)->toBe('existing')->and($sent)->toBe(0);
    // unknown outcome, effect applied: lookup returns it
    $r = LookupBeforeRetry::execute(fn () => throw new RequiresInterventionError('CBA.TRANSPORT.TIMEOUT_UNKNOWN_OUTCOME', 't', true), fn () => 'applied', false);
    expect($r)->toBe('applied');
    // unknown outcome, not applied: retryable with the same key
    LookupBeforeRetry::execute(fn () => throw new RequiresInterventionError('CBA.TRANSPORT.TIMEOUT_UNKNOWN_OUTCOME', 't', true), fn () => null, false);
})->throws(RetryableError::class, 'safe to retry')->group('FR-CBA-007');

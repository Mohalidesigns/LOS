<?php

declare(strict_types=1);

use Fundly\Modules\Access\Domain\AccessPolicy;
use Fundly\Modules\Access\Domain\Grant;
use Fundly\Modules\Access\Domain\LockoutPolicy;
use Fundly\Modules\Access\Domain\Scope;
use Fundly\Modules\Access\Domain\SodPolicy;
use Fundly\Modules\Access\Domain\SodRule;
use Fundly\Shared\Money\Money;
use Fundly\Shared\Security\ResourceAttributes;

function grant(array $perms, Scope $scope = new Scope, ?array $subtree = null, string $from = '-1 day', ?string $to = null, string $id = 'a1'): Grant
{
    return new Grant($id, 'r1', 'role', $perms, $scope, new DateTimeImmutable($from), $to === null ? null : new DateTimeImmutable($to), $subtree);
}

$now = new DateTimeImmutable;

it('denies by default when nothing grants the permission', function () use ($now) {
    expect(AccessPolicy::decide([], 'user:read', null, $now)->allowed)->toBeFalse()
        ->and(AccessPolicy::decide([grant(['role:read'])], 'user:read', null, $now)->reason)->toBe('permission_not_granted');
})->group('FR-SEC-003');

it('allows through the union of assignments and names the granting assignment', function () use ($now) {
    $d = AccessPolicy::decide([grant(['role:read'], id: 'x'), grant(['user:read'], id: 'y')], 'user:read', null, $now);
    expect($d->allowed)->toBeTrue()->and($d->grantingAssignmentId)->toBe('y');
})->group('FR-SEC-004', 'FR-SEC-011');

it('applies every scope dimension, combined within one assignment', function () use ($now) {
    $scope = new Scope(legalEntityIds: ['le1'], orgUnitId: 'ou1', productIds: ['p1'], currencies: ['NGN'], maxAmount: Money::of('5000000', 'NGN'), segments: ['retail'], portfolioTags: ['salary']);
    $g = [grant(['application:approve'], $scope, ['ou1', 'ou1a', 'ou1b'])];
    $ok = new ResourceAttributes('le1', 'ou1b', 'p1', 'NGN', Money::of('4999999.99', 'NGN'), 'retail', ['salary']);
    expect(AccessPolicy::decide($g, 'application:approve', $ok, $now)->allowed)->toBeTrue();

    $variants = [
        'legal entity' => new ResourceAttributes('le2', 'ou1b', 'p1', 'NGN', Money::of('1', 'NGN'), 'retail', ['salary']),
        'org subtree' => new ResourceAttributes('le1', 'ou9', 'p1', 'NGN', Money::of('1', 'NGN'), 'retail', ['salary']),
        'product' => new ResourceAttributes('le1', 'ou1', 'p2', 'NGN', Money::of('1', 'NGN'), 'retail', ['salary']),
        'currency' => new ResourceAttributes('le1', 'ou1', 'p1', 'USD', Money::of('1', 'USD'), 'retail', ['salary']),
        'amount band' => new ResourceAttributes('le1', 'ou1', 'p1', 'NGN', Money::of('5000000.01', 'NGN'), 'retail', ['salary']),
        'segment' => new ResourceAttributes('le1', 'ou1', 'p1', 'NGN', Money::of('1', 'NGN'), 'corporate', ['salary']),
        'portfolio tag' => new ResourceAttributes('le1', 'ou1', 'p1', 'NGN', Money::of('1', 'NGN'), 'retail', ['sme']),
    ];
    foreach ($variants as $dimension => $r) {
        $d = AccessPolicy::decide($g, 'application:approve', $r, $now);
        expect($d->allowed)->toBeFalse("{$dimension} should be out of scope")->and($d->reason)->toBe('out_of_scope');
    }
})->group('FR-SEC-005');

it('honours valid_from and valid_to (time-bound access)', function () use ($now) {
    expect(AccessPolicy::decide([grant(['user:read'], from: '+1 hour')], 'user:read', null, $now)->allowed)->toBeFalse()
        ->and(AccessPolicy::decide([grant(['user:read'], from: '-2 days', to: '-1 day')], 'user:read', null, $now)->allowed)->toBeFalse()
        ->and(AccessPolicy::decide([grant(['user:read'], from: '-2 days', to: '+1 day')], 'user:read', null, $now)->allowed)->toBeTrue();
})->group('FR-SEC-008');

it('lets a token only narrow access, never widen it', function () use ($now) {
    $g = [grant(['user:read', 'role:read'])];
    expect(AccessPolicy::decide($g, 'user:read', null, $now, ['role:read'])->reason)->toBe('token_ability_missing')
        ->and(AccessPolicy::decide($g, 'role:read', null, $now, ['role:read'])->allowed)->toBeTrue()
        ->and(AccessPolicy::decide($g, 'user:manage', null, $now, ['*'])->allowed)->toBeFalse();
})->group('FR-SEC-014');

it('detects SoD violations across combined holdings', function () {
    $rules = [new SodRule('s1', SodRule::PERMISSION_PAIR, 'disbursement:make', 'disbursement:check', 'maker/checker'), new SodRule('s2', SodRule::ROLE_PAIR, 'roleA', 'roleB', 'pair')];
    expect(SodPolicy::violations(['disbursement:make'], ['roleA'], $rules))->toBe([])
        ->and(SodPolicy::violations(['disbursement:make', 'disbursement:check'], [], $rules))->toHaveCount(1)
        ->and(SodPolicy::violations([], ['roleA', 'roleB'], $rules)[0]->id)->toBe('s2');
})->group('FR-SEC-006');

it('locks accounts with exponential backoff after the threshold', function () {
    $p = new LockoutPolicy(5, 1, 60);
    $now = new DateTimeImmutable('2026-10-08T10:00:00Z');
    expect($p->lockUntil(4, $now))->toBeNull()
        ->and($p->lockUntil(5, $now)?->format('H:i'))->toBe('10:01')
        ->and($p->lockUntil(7, $now)?->format('H:i'))->toBe('10:04')
        ->and($p->lockUntil(30, $now)?->format('H:i'))->toBe('11:00');
})->group('LOS-FR-302', 'FR-SEC-019');

<?php

declare(strict_types=1);

use Fundly\Modules\Approval\Domain\ApprovalChain;
use Fundly\Modules\Approval\Domain\ApproverEligibility;
use Fundly\Modules\Approval\Domain\AuthorityLimits;
use Fundly\Modules\Approval\Domain\MatrixDefinition;
use Fundly\Shared\Money\Money;
use Tests\Support\LendingFixtures;

function authority(string $role, ?string $limit, ?string $delegation = null): array
{
    return ['assignment_id' => 'a-'.$role, 'role_code' => $role, 'role_name' => $role, 'delegation_id' => $delegation, 'delegated_by' => $delegation === null ? null : 'boss',
        'limit' => $limit === null ? null : Money::of($limit, 'NGN'), 'limit_source' => $limit === null ? null : 'role', 'limit_components' => []];
}

it('escalates on exceptions only at or above the configured severity', function () {
    $m = MatrixDefinition::of(LendingFixtures::smeMatrix());
    $staffed = static fn (array $l): bool => true;
    expect(array_column(ApprovalChain::build($m, ['L1'], $m->exceptionEscalates(1, 'low'), $staffed), 'code'))->toBe(['L1'])
        ->and(array_column(ApprovalChain::build($m, ['L1'], $m->exceptionEscalates(1, 'high'), $staffed), 'code'))->toBe(['L1', 'L2'])
        // already at the top: nothing above to add
        ->and(array_column(ApprovalChain::build($m, ['L1', 'L2', 'L3'], true, $staffed), 'code'))->toBe(['L1', 'L2', 'L3']);
})->group('FR-APV-007');

it('replaces unstaffed levels with the next one up, keeping the top level even if unstaffed', function () {
    $m = MatrixDefinition::of(LendingFixtures::smeMatrix());
    $chain = ApprovalChain::build($m, ['L1', 'L2'], false, static fn (array $l): bool => $l['code'] === 'L3');
    expect(array_map(static fn (array $l): array => [$l['code'], $l['added_by']], $chain))->toBe([['L3', 'no_eligible_approver']]);
    $none = ApprovalChain::build($m, ['L3'], false, static fn (array $l): bool => false);
    expect(array_column($none, 'code'))->toBe(['L3']);
    $off = MatrixDefinition::of(LendingFixtures::smeMatrix(['escalation' => ['on_no_eligible_approver' => false]]));
    expect(array_column(ApprovalChain::build($off, ['L1'], false, static fn (array $l): bool => false), 'code'))->toBe(['L1']);
})->group('FR-APV-007');

it('takes the minimum applicable limit; a limit in another currency allows nothing; no limit means no authority', function () {
    $limits = AuthorityLimits::of(LendingFixtures::authorityLimits(['users' => ['u1' => '3000000.00']]));
    $e = $limits->effective('credit_approver', Money::of('10000000', 'NGN'), ['u1'], []);
    expect($e['source'])->toBe('user')->and((string) $e['limit']?->amount)->toBe('3000000.0000');
    $usd = $limits->effective('credit_approver', Money::of('1000000', 'USD'), [], []);
    expect($usd['limit']?->isZero())->toBeTrue();
    expect(AuthorityLimits::none('NGN')->effective('credit_approver', null, ['u1'], [])['limit'])->toBeNull();
})->group('FR-APV-002');

it('bars originator, recommender and earlier voters, and prefers own authority over a delegation', function () {
    $exposure = Money::of('8000000', 'NGN');
    $roles = ['head_of_credit'];
    $mine = [authority('head_of_credit', '100000000')];
    expect(ApproverEligibility::assess('u', 'u', null, [], $roles, $mine, $exposure)['blockers'][0])->toContain('originated')
        ->and(ApproverEligibility::assess('u', 'o', 'u', [], $roles, $mine, $exposure)['blockers'][0])->toContain('recommended')
        ->and(ApproverEligibility::assess('u', 'o', 'r', ['u'], $roles, $mine, $exposure)['blockers'][0])->toContain('lower level');
    $both = ApproverEligibility::assess('u', 'o', 'r', [], $roles, [authority('head_of_credit', '500000000', 'd1'), authority('head_of_credit', '90000000')], $exposure);
    expect($both['eligible'])->toBeTrue()->and($both['basis']['delegation_id'])->toBeNull();
    $short = ApproverEligibility::assess('u', 'o', 'r', [], $roles, [authority('head_of_credit', '5000000')], $exposure);
    expect($short['eligible'])->toBeFalse()->and($short['blockers'][0])->toContain('below the exposure');
    expect(ApproverEligibility::assess('u', 'o', 'r', [], $roles, [authority('head_of_credit', null)], $exposure)['blockers'][0])->toContain('no authority');
})->group('FR-APV-005', 'FR-APV-006');

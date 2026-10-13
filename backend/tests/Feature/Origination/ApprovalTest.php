<?php

declare(strict_types=1);

use Fundly\Modules\Access\Domain\Permission;
use Fundly\Modules\Access\Infrastructure\Persistence\GrantRepository;
use Fundly\Modules\Application\Contracts\ApplicationLifecycle;
use Fundly\Modules\Application\Contracts\CanonicalStatus;
use Fundly\Modules\Approval\Contracts\ApprovalRegister;
use Fundly\Shared\Security\Principal;
use Fundly\Shared\Security\SystemIdentity;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\LendingFixtures;

beforeEach(function () {
    $this->tenant = $this->provisionTenant();
    LendingFixtures::bindSimulators();
    $this->org = LendingFixtures::orgTree($this);
    LendingFixtures::activateProduct($this, 'sme-term-loan', 'SME Term Loan', LendingFixtures::smeTermLoan());
    LendingFixtures::activateConfig($this, 'credit.rule_set', 'sme-policy', 'SME credit policy', LendingFixtures::smePolicy());
    $this->matrix = LendingFixtures::activateConfig($this, 'approval.matrix', 'sme-matrix', 'SME approval matrix', LendingFixtures::smeMatrix());
    LendingFixtures::activateConfig($this, 'approval.authority_limits', 'default', 'Authority limits', LendingFixtures::authorityLimits());
    $this->rm = $this->userWith([Permission::ApplicationView, Permission::ApplicationOriginate, Permission::PartyManage]);
    $this->analyst = $this->userWith([Permission::ApplicationView, Permission::CreditAnalyse, Permission::BureauPull, Permission::ApplicationRecommend, Permission::ExceptionRaise]);
    $approver = [Permission::ApplicationView, Permission::ApplicationApprove, Permission::DelegationCreate];
    $this->l1 = $this->userWith($approver, roleCode: 'credit_approver');
    $this->l2 = $this->userWith($approver, roleCode: 'head_of_credit');
    $this->l3 = $this->userWith($approver, roleCode: 'chief_credit_officer');
});

/** A company application taken through bureau, decision and memo, then recommended by the analyst. */
function recommendedCase(object $t, string $amount = '8000000.00', ?string $exceptionSeverity = null, string $rc = 'RC1234567'): array
{
    $t->login($t->rm);
    $company = LendingFixtures::company($t, 'Case '.$rc.' Limited', $rc, $t->org['lagos']['id']);
    LendingFixtures::consent($t, $company);
    $app = $t->api('POST', '/api/v1/applications', [
        'legal_entity_id' => $t->org['le']['id'], 'org_unit_id' => $t->org['lagos']['id'], 'product_key' => 'sme-term-loan', 'primary_party_id' => $company['id'],
        'requested_amount' => $amount, 'tenor_months' => 24, 'purpose' => 'Equipment', 'data' => ['monthly_income' => '9000000.00', 'years_trading' => 9],
    ])->assertCreated()->json('data');
    $etag = fn (): string => (string) $t->api('GET', "/api/v1/applications/{$app['id']}")->headers->get('ETag');
    $t->api('POST', "/api/v1/applications/{$app['id']}/actions/submit", [], ['If-Match' => $etag()])->assertOk();
    $system = Principal::system($t->currentTenantFixture()->id, SystemIdentity::Workflow);
    foreach ([CanonicalStatus::Documentation, CanonicalStatus::Assessment] as $to) {
        app(ApplicationLifecycle::class)->advance($app['id'], $to, 'TEST_SETUP', null, $system);
    }
    $t->login($t->analyst);
    $t->api('POST', "/api/v1/applications/{$app['id']}/bureau-reports/actions/pull")->assertCreated();
    $decision = $t->api('POST', "/api/v1/applications/{$app['id']}/decisions/actions/run")->assertCreated()->json('data');
    if ($exceptionSeverity !== null) {
        $t->api('POST', "/api/v1/decisions/{$decision['id']}/exceptions", ['reason_code' => $decision['reason_codes'][0]['code'], 'justification' => 'Override agreed with the business: strong collateral offsets the policy hit.', 'severity' => $exceptionSeverity])->assertCreated();
    }
    $t->api('POST', "/api/v1/applications/{$app['id']}/credit-memo", ['narrative' => 'Strong trading history and affordable repayments.', 'recommendation' => 'approve'])->assertCreated();
    $t->api('POST', "/api/v1/applications/{$app['id']}/actions/recommend", [], ['If-Match' => $etag()])->assertOk();

    return ['app' => $app, 'decision' => $decision, 'request' => $t->api('GET', "/api/v1/applications/{$app['id']}/approval-requests")->json('data.0')];
}

function appStatus(object $t, string $id): string
{
    return (string) DB::table('applications')->where('id', $id)->value('canonical_status');
}

function vote(object $t, string $requestId, array $body): TestResponse
{
    return $t->api('POST', "/api/v1/approval-requests/{$requestId}/actions/vote", $body + ['rationale' => 'Within appetite; cash flow covers the instalment comfortably.']);
}

it('validates approval matrices and authority limits as configuration (FR-APV-001/002/003)', function () {
    $author = $this->userWith([Permission::ConfigRead, Permission::ConfigAuthor]);
    $this->login($author);
    $artifact = $this->api('POST', '/api/v1/config-artifacts/approval.matrix', ['key' => 'bad', 'name' => 'Bad'])->assertCreated()->json('data');
    $bad = LendingFixtures::smeMatrix([
        'levels' => [2 => ['mode' => 'quorum']],
        'routing' => ['rows' => [0 => ['when' => 'salary.amount > 1'], 1 => ['outputs' => ['levels' => ['L9']]]]],
        'step_up' => ['above_amount' => 'lots'],
    ]);
    $errors = $this->api('POST', "/api/v1/config-artifacts/approval.matrix/{$artifact['id']}/versions", ['content' => $bad])->assertStatus(422)->json('errors');
    expect(array_keys($errors))->toContain('content.levels.2.mode', 'content.routing.rows.0.when', 'content.routing.rows.1.outputs.levels', 'content.step_up.above_amount')
        ->and($errors['content.levels.2.mode'][0])->toContain('P3');

    $limits = $this->api('POST', '/api/v1/config-artifacts/approval.authority_limits', ['key' => 'bad', 'name' => 'Bad'])->assertCreated()->json('data');
    $errors = $this->api('POST', "/api/v1/config-artifacts/approval.authority_limits/{$limits['id']}/versions", ['content' => ['currency' => 'naira', 'roles' => ['Credit Approver' => 5000000]]])->assertStatus(422)->json('errors');
    expect(array_keys($errors))->toContain('content.currency', 'content.roles.Credit Approver');
})->group('FR-APV-001', 'FR-APV-002', 'FR-APV-003');

it('routes a recommended application through the matrix into Approval, recording the matrix row, facts and chain', function () {
    $case = recommendedCase($this, amount: '12500000.00');
    $r = $case['request'];
    expect(appStatus($this, $case['app']['id']))->toBe('approval')
        ->and($r)->toMatchArray(['status' => 'pending', 'current_level' => 0, 'validity_days' => 60, 'decision_id' => $case['decision']['id']])
        ->and($r['matrix'])->toMatchArray(['key' => 'sme-matrix', 'version_id' => $this->matrix['version']['id'], 'row' => 1])
        ->and(collect($r['levels'])->pluck('code')->all())->toBe(['L1', 'L2'])
        ->and(collect($r['levels'])->pluck('added_by')->unique()->all())->toBe(['matrix'])
        ->and($r['exposure'])->toBe(['amount' => '12500000.0000', 'currency' => 'NGN'])
        ->and($r['facts']['decision']['risk_grade'])->toBe($case['decision']['risk_grade'])
        ->and($r['recommended_by']['id'])->toBe($this->analyst->id)
        ->and(collect($r['routing_trace'])->pluck('result')->all())->toBe([false, true]);
    $timeline = DB::table('application_events')->where('application_id', $case['app']['id'])->where('type', 'application.status_changed')->orderByDesc('version')->first();
    expect(json_decode((string) $timeline->payload, true))->toMatchArray(['from' => 'recommended', 'to' => 'approval', 'reason_code' => 'APPROVAL_ROUTED']);
})->group('FR-APV-001', 'FR-APV-003');

it('runs demo step 11: exception escalates a level, the originator and recommender are refused, L1 approves with conditions, L2 approves with step-up', function () {
    $case = recommendedCase($this, exceptionSeverity: 'medium');
    $r = $case['request'];
    expect(collect($r['levels'])->map(fn ($l) => [$l['code'], $l['added_by']])->all())->toBe([['L1', 'matrix'], ['L2', 'exception']]);

    // the RM (originator) holds no approval authority at all
    $this->login($this->rm);
    vote($this, $r['id'], ['decision' => 'approve'])->assertForbidden();
    // an originator who also holds an approver role is still refused (FR-APV-005)
    DB::table('role_assignments')->insert(['id' => (string) Str::uuid7(), 'tenant_id' => $this->tenant->id, 'user_id' => $this->rm->id, 'role_id' => DB::table('roles')->where('code', 'credit_approver')->value('id'), 'scope' => '{}', 'valid_from' => now()->subMinute(), 'granted_by' => 'test-fixture', 'created_at' => now(), 'updated_at' => now()]);
    app(GrantRepository::class)->forget();
    $this->login($this->rm);
    $refused = vote($this, $r['id'], ['decision' => 'approve'])->assertForbidden();
    expect($refused->json('detail'))->toContain('originated')->toContain('FR-APV-005');
    expect(DB::table('audit_events')->where('action', 'authz.denied')->where('reason_code', 'approver_sod')->exists())->toBeTrue();

    $this->login($this->l1);
    $packet = $this->api('GET', "/api/v1/approval-requests/{$r['id']}")->assertOk()->json('data');
    expect($packet['my_eligibility'])->toMatchArray(['can_vote' => true, 'step_up_required' => true])
        ->and($packet['packet']['credit'])->toMatchArray(['exception_count' => 1, 'max_exception_severity' => 'medium', 'is_routed_decision' => true])
        ->and($packet['packet']['bureau_valid'])->toBeTrue();
    vote($this, $r['id'], ['decision' => 'approve_with_conditions', 'conditions' => [['kind' => 'precedent', 'description' => 'Personal guarantee of the MD']]])->assertStatus(422)->assertJsonValidationErrors(['reason_codes']);
    vote($this, $r['id'], ['decision' => 'approve', 'rationale' => 'ok'])->assertStatus(422)->assertJsonValidationErrors(['rationale']);
    $after = vote($this, $r['id'], ['decision' => 'approve_with_conditions', 'reason_codes' => ['COND_GUARANTEE'], 'conditions' => [
        ['kind' => 'precedent', 'description' => 'Personal guarantee of the MD'],
        ['kind' => 'subsequent', 'description' => 'Quarterly management accounts within 45 days of quarter end'],
    ]])->assertOk()->json('data');
    expect($after)->toMatchArray(['status' => 'pending', 'current_level' => 1])
        ->and($after['votes'][0])->toMatchArray(['level_code' => 'L1', 'decision' => 'approve_with_conditions', 'reason_codes' => ['COND_GUARANTEE'], 'stepped_up' => true])
        ->and($after['votes'][0]['authority_basis'])->toMatchArray(['role_code' => 'credit_approver', 'limit_source' => 'role', 'effective_limit' => ['amount' => '20000000.0000', 'currency' => 'NGN'], 'delegation_id' => null])
        ->and($after['votes'][0]['authority_basis']['matrix'])->toEqualCanonicalizing(['key' => 'sme-matrix', 'version_no' => 1, 'row' => 0])
        ->and(collect($after['conditions'])->pluck('kind')->all())->toBe(['precedent', 'subsequent'])
        ->and(appStatus($this, $case['app']['id']))->toBe('approval');
    // L1 cannot vote again at L2
    $this->api('GET', '/api/v1/approval-requests')->assertOk()->assertJsonCount(0, 'data');

    // L2: the sign-in step-up has gone stale; a fresh one is required above the threshold
    $this->login($this->l2);
    expect($this->api('GET', '/api/v1/approval-requests')->json('data.0.id'))->toBe($r['id']);
    $this->travel(6)->minutes();
    vote($this, $r['id'], ['decision' => 'approve'])->assertStatus(401)->assertJsonPath('type', fn ($t) => str_ends_with($t, 'step-up-required'));
    $this->stepUp($this->l2);
    $done = vote($this, $r['id'], ['decision' => 'approve'])->assertOk()->json('data');
    expect($done['status'])->toBe('approved')
        ->and($done['votes'][1])->toMatchArray(['level_code' => 'L2', 'decision' => 'approve', 'stepped_up' => true])
        ->and($done['votes'][1]['authority_basis']['level'])->toMatchArray(['code' => 'L2', 'added_by' => 'exception'])
        ->and(substr($done['valid_until'], 0, 10))->toBe(now()->addDays(60)->toDateString())
        ->and(appStatus($this, $case['app']['id']))->toBe('approved');
    $standing = app(ApprovalRegister::class)->current($case['app']['id']);
    expect($standing->isValidAt(now()->toDateTimeImmutable()))->toBeTrue()
        ->and(count($standing->conditions))->toBe(2)
        ->and($standing->conditions[0]->levelCode)->toBe('L1');
    expect(DB::table('audit_events')->where('action', 'approval.vote.cast')->where('entity_id', $r['id'])->whereNotNull('step_up_ref')->count())->toBe(2);
    // votes are evidence
    expect(fn () => DB::transaction(fn () => DB::table('approval_votes')->where('request_id', $r['id'])->update(['decision' => 'decline'])))->toThrow(QueryException::class);
})->group('FR-APV-005', 'FR-APV-007', 'FR-APV-008', 'FR-APV-009', 'FR-APV-010', 'FR-SEC-013');

it('refuses the recommender even when they hold approval authority', function () {
    DB::table('role_assignments')->insert(['id' => (string) Str::uuid7(), 'tenant_id' => $this->tenant->id, 'user_id' => $this->analyst->id, 'role_id' => DB::table('roles')->where('code', 'credit_approver')->value('id'), 'scope' => '{}', 'valid_from' => now()->subMinute(), 'granted_by' => 'test-fixture', 'created_at' => now(), 'updated_at' => now()]);
    $case = recommendedCase($this);
    $this->login($this->analyst);
    $eligibility = $this->api('GET', "/api/v1/approval-requests/{$case['request']['id']}")->json('data.my_eligibility');
    expect($eligibility['can_vote'])->toBeFalse()->and($eligibility['blockers'][0])->toContain('recommended');
    vote($this, $case['request']['id'], ['decision' => 'approve'])->assertForbidden();
})->group('FR-APV-005');

it('uses the most restrictive limit and escalates a level nobody can approve (FR-APV-002/007)', function () {
    // the only L1 approver's assignment scope caps them at 5m, below the 8m exposure
    DB::table('role_assignments')->where('user_id', $this->l1->id)->update(['scope' => json_encode(['max_amount' => ['amount' => '5000000.00', 'currency' => 'NGN']])]);
    $case = recommendedCase($this);
    $r = $case['request'];
    expect(collect($r['levels'])->map(fn ($l) => [$l['code'], $l['added_by']])->all())->toBe([['L2', 'no_eligible_approver']])
        ->and($r['levels'][0]['note'])->toContain('No eligible approver at L1');

    $this->login($this->l1);
    $e = $this->api('GET', "/api/v1/approval-requests/{$r['id']}")->json('data.my_eligibility');
    expect($e['can_vote'])->toBeFalse()->and(implode(' ', $e['blockers']))->toContain('None of your roles is eligible at this authority level (head_of_credit)');
})->group('FR-APV-002', 'FR-APV-007');

it('applies role, org-unit and user limits, refusing an approver whose effective limit is below the exposure', function () {
    LendingFixtures::activateConfig($this, 'approval.authority_limits', 'default', 'Authority limits', LendingFixtures::authorityLimits([
        'org_units' => [$this->org['lagos']['id'] => '9000000.00'],
        'users' => [$this->l1->id => '7500000.00'],
    ]));
    DB::table('users')->where('id', $this->l1->id)->update(['home_org_unit_id' => $this->org['lagos']['id']]);
    $second = $this->userWith([Permission::ApplicationView, Permission::ApplicationApprove], roleCode: 'credit_approver', homeOrgUnitId: $this->org['lagos']['id']);
    $case = recommendedCase($this);
    $r = $case['request'];
    expect(collect($r['levels'])->pluck('code')->all())->toBe(['L1']);

    $this->login($this->l1);
    $refused = vote($this, $r['id'], ['decision' => 'approve'])->assertForbidden();
    expect($refused->json('detail'))->toContain('NGN 7500000.0000, from user')->toContain('FR-APV-002');

    $this->login($second);
    $done = vote($this, $r['id'], ['decision' => 'approve'])->assertOk()->json('data');
    expect($done['votes'][0]['authority_basis'])->toMatchArray(['limit_source' => 'org_unit', 'effective_limit' => ['amount' => '9000000.0000', 'currency' => 'NGN']])
        ->and(collect($done['votes'][0]['authority_basis']['limit_components'])->pluck('source')->all())->toBe(['role', 'org_unit']);
})->group('FR-APV-002');

it('lets a delegate approve within the delegator\'s cap and records the delegation as authority basis (FR-APV-006)', function () {
    $deputy = $this->userWith([Permission::ApplicationView]);
    $assignment = (string) DB::table('role_assignments')->where('user_id', $this->l1->id)->value('id');
    $this->login($this->l1);
    $this->api('POST', '/api/v1/delegations', ['role_assignment_id' => $assignment, 'delegate_id' => $deputy->id, 'valid_from' => now()->subMinute()->toIso8601String(), 'valid_to' => now()->addDays(5)->toIso8601String(), 'reason' => 'Annual leave'])->assertCreated();
    LendingFixtures::activateConfig($this, 'approval.authority_limits', 'default', 'Authority limits', LendingFixtures::authorityLimits(['users' => [$this->l1->id => '9000000.00']]));
    $case = recommendedCase($this);

    $this->login($deputy);
    $done = vote($this, $case['request']['id'], ['decision' => 'approve'])->assertOk()->json('data');
    expect($done['votes'][0]['authority_basis'])->toMatchArray(['role_code' => 'credit_approver', 'delegated_by' => $this->l1->id, 'limit_source' => 'user', 'effective_limit' => ['amount' => '9000000.0000', 'currency' => 'NGN']])
        ->and($done['votes'][0]['authority_basis']['delegation_id'])->not->toBeNull();
})->group('FR-APV-006');

it('declines with reason codes, and counter-offers only below the requested amount', function () {
    $declined = recommendedCase($this);
    $this->login($this->l1);
    vote($this, $declined['request']['id'], ['decision' => 'decline'])->assertStatus(422)->assertJsonValidationErrors(['reason_codes']);
    vote($this, $declined['request']['id'], ['decision' => 'decline', 'reason_codes' => ['DEC_SECTOR_RISK']])->assertOk()->assertJsonPath('data.status', 'declined');
    expect(appStatus($this, $declined['app']['id']))->toBe('declined');
    vote($this, $declined['request']['id'], ['decision' => 'approve'])->assertStatus(422);

    $countered = recommendedCase($this, rc: 'RC7654321');
    $this->login($this->l1);
    vote($this, $countered['request']['id'], ['decision' => 'counter_offer', 'reason_codes' => ['CTR_AFFORDABILITY'], 'counter_terms' => ['amount' => '9000000.00', 'tenor_months' => 24]])->assertStatus(422)->assertJsonValidationErrors(['counter_terms.amount']);
    $done = vote($this, $countered['request']['id'], ['decision' => 'counter_offer', 'reason_codes' => ['CTR_AFFORDABILITY'], 'counter_terms' => ['amount' => '6000000.00', 'tenor_months' => 18]])->assertOk()->json('data');
    expect($done['status'])->toBe('counter_offered')->and($done['counter_terms'])->toBe(['amount' => ['amount' => '6000000.0000', 'currency' => 'NGN'], 'tenor_months' => 18])
        ->and(appStatus($this, $countered['app']['id']))->toBe('counter_offered')
        ->and(app(ApprovalRegister::class)->current($countered['app']['id'])->counterAmount->toArray())->toBe(['amount' => '6000000.0000', 'currency' => 'NGN']);
})->group('FR-APV-008');

it('expires approvals past their validity instead of letting them lapse silently (FR-APV-012)', function () {
    $case = recommendedCase($this);
    $this->login($this->l1);
    vote($this, $case['request']['id'], ['decision' => 'approve'])->assertOk();
    Artisan::call('approvals:expire');
    expect(appStatus($this, $case['app']['id']))->toBe('approved');

    $this->travel(61)->days();
    expect(app(ApprovalRegister::class)->current($case['app']['id'])->isValidAt(now()->toDateTimeImmutable()))->toBeFalse();
    Artisan::call('approvals:expire');
    expect(appStatus($this, $case['app']['id']))->toBe('expired')
        ->and(DB::table('approval_requests')->where('id', $case['request']['id'])->value('status'))->toBe('expired');
    expect(json_decode((string) DB::table('application_events')->where('application_id', $case['app']['id'])->orderByDesc('version')->value('payload'), true))
        ->toMatchArray(['to' => 'expired', 'reason_code' => 'APPROVAL_VALIDITY_LAPSED']);
})->group('FR-APV-012');

it('leaves the application Recommended when the product binds no active matrix', function () {
    LendingFixtures::activateProduct($this, 'sme-term-loan', 'SME Term Loan', LendingFixtures::smeTermLoan(['bindings' => ['approval_matrix' => 'missing-matrix']]));
    $id = recommendedCase($this)['app']['id'];
    expect(appStatus($this, $id))->toBe('recommended')
        ->and(DB::table('approval_requests')->where('application_id', $id)->exists())->toBeFalse();
});

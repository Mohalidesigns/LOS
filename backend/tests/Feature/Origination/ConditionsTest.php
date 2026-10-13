<?php

declare(strict_types=1);

use Fundly\Integration\Runtime\Models\AdapterBinding;
use Fundly\Modules\Access\Domain\Permission;
use Fundly\Modules\Application\Contracts\ApplicationLifecycle;
use Fundly\Modules\Application\Contracts\CanonicalStatus;
use Fundly\Modules\Conditions\Contracts\ConditionsGate;
use Fundly\Shared\Security\Principal;
use Fundly\Shared\Security\SystemIdentity;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\Support\LendingFixtures;

beforeEach(function () {
    $this->tenant = $this->provisionTenant();
    LendingFixtures::bindSimulators();
    AdapterBinding::query()->where('port', 'core_banking')->update(['config' => ['account_names' => ['0123456789' => 'NWOSU AMAKA', '0123456790' => 'JOHN DOE TRADING']]]);
    $this->org = LendingFixtures::orgTree($this);
    LendingFixtures::activateProduct($this, 'sme-term-loan', 'SME Term Loan', LendingFixtures::smeTermLoan());
    LendingFixtures::activateConfig($this, 'credit.rule_set', 'sme-policy', 'SME credit policy', LendingFixtures::smePolicy());
    LendingFixtures::activateConfig($this, 'approval.matrix', 'sme-matrix', 'SME approval matrix', LendingFixtures::smeMatrix());
    LendingFixtures::activateConfig($this, 'approval.authority_limits', 'default', 'Authority limits', LendingFixtures::authorityLimits());
    LendingFixtures::activateConfig($this, 'offer.template', 'sme-offer', 'SME offer letter', LendingFixtures::offerTemplate());
    $this->rm = $this->userWith([Permission::ApplicationView, Permission::ApplicationOriginate, Permission::PartyManage]);
    $this->analyst = $this->userWith([Permission::ApplicationView, Permission::CreditAnalyse, Permission::BureauPull, Permission::ApplicationRecommend]);
    $this->approver = $this->userWith([Permission::ApplicationView, Permission::ApplicationApprove], roleCode: 'credit_approver');
    $this->ops = $this->userWith([Permission::ApplicationView, Permission::OfferPrepare, Permission::ConditionManage]);
    $this->checker = $this->userWith([Permission::ApplicationView, Permission::ConditionWaiveApprove, Permission::ChangeRequestRead]);
});

/**
 * An individual borrower with a guarantor (both KYC-verified), approved with
 * one CP and one CS, offered, signed and accepted.
 *
 * @return array{app: array<string, mixed>}
 */
function acceptedCase(object $t, string $guarantorFirst = 'Funke', string $guarantorLast = 'Adebayo'): array
{
    $t->login($t->rm);
    $borrower = LendingFixtures::person($t, 'Amaka', 'Nwosu', '22255566677', $t->org['lagos']['id']);
    $guarantor = LendingFixtures::person($t, $guarantorFirst, $guarantorLast, '22212345678', $t->org['lagos']['id']);
    LendingFixtures::consent($t, $borrower);
    LendingFixtures::consent($t, $guarantor, ['data_processing']);
    foreach ([$borrower, $guarantor] as $p) {
        $t->api('POST', "/api/v1/parties/{$p['id']}/identities/bvn/actions/verify")->assertOk();
    }
    $app = $t->api('POST', '/api/v1/applications', [
        'legal_entity_id' => $t->org['le']['id'], 'org_unit_id' => $t->org['lagos']['id'], 'product_key' => 'sme-term-loan', 'primary_party_id' => $borrower['id'],
        'requested_amount' => '3000000.00', 'tenor_months' => 12, 'purpose' => 'Inventory', 'data' => ['monthly_income' => '2500000.00', 'years_trading' => 5],
    ])->assertCreated()->json('data');
    $etag = fn (): string => (string) $t->api('GET', "/api/v1/applications/{$app['id']}")->headers->get('ETag');
    $t->api('POST', "/api/v1/applications/{$app['id']}/applicants", ['party_id' => $guarantor['id'], 'role' => 'guarantor'], ['If-Match' => $etag()])->assertCreated();
    $t->api('POST', "/api/v1/applications/{$app['id']}/actions/submit", [], ['If-Match' => $etag()])->assertOk();
    $system = Principal::system($t->currentTenantFixture()->id, SystemIdentity::Workflow);
    foreach ([CanonicalStatus::Documentation, CanonicalStatus::Assessment] as $to) {
        app(ApplicationLifecycle::class)->advance($app['id'], $to, 'TEST_SETUP', null, $system);
    }
    $t->login($t->analyst);
    $t->api('POST', "/api/v1/applications/{$app['id']}/bureau-reports/actions/pull")->assertCreated();
    $t->api('POST', "/api/v1/applications/{$app['id']}/decisions/actions/run")->assertCreated();
    $t->api('POST', "/api/v1/applications/{$app['id']}/credit-memo", ['narrative' => 'Five years trading, affordable on stated income.', 'recommendation' => 'approve'])->assertCreated();
    $t->api('POST', "/api/v1/applications/{$app['id']}/actions/recommend", [], ['If-Match' => $etag()])->assertOk();
    $request = $t->api('GET', "/api/v1/applications/{$app['id']}/approval-requests")->json('data.0');
    $t->login($t->approver);
    $t->api('POST', "/api/v1/approval-requests/{$request['id']}/actions/vote", [
        'decision' => 'approve_with_conditions', 'rationale' => 'Affordable; guarantee strengthens the case.', 'reason_codes' => ['COND_GUARANTEE'],
        'conditions' => [['kind' => 'precedent', 'description' => 'Executed deed of guarantee from the guarantor'], ['kind' => 'subsequent', 'description' => 'Quarterly stock report for the first year']],
    ])->assertOk();
    $t->login($t->ops);
    $offer = $t->api('POST', "/api/v1/applications/{$app['id']}/offers")->assertCreated()->json('data');
    foreach ($offer['signatories'] as $s) {
        $t->api('POST', "/api/v1/offers/{$offer['id']}/signatories/{$s['id']}/actions/sign", ['signed_at' => now()->toIso8601String()])->assertOk();
    }
    $t->api('POST', "/api/v1/offers/{$offer['id']}/actions/accept", ['file' => UploadedFile::fake()->createWithContent('executed.pdf', "%PDF-1.4\n%%EOF\n")])->assertOk();

    return ['app' => $app];
}

function conditionsOf(object $t, string $applicationId): array
{
    return $t->api('GET', "/api/v1/applications/{$applicationId}/conditions")->assertOk()->json('data');
}

function runPreDisbursementCheck(object $t, string $applicationId, string $account = '0123456789'): array
{
    return $t->api('POST', "/api/v1/applications/{$applicationId}/pre-disbursement-checks", ['bank_code' => '058', 'account_no' => $account])->assertCreated()->json('data');
}

it('runs demo step 14: CPs from the approval block release until met or waived, and the final check passes only then', function () {
    $case = acceptedCase($this);
    $id = $case['app']['id'];
    $this->login($this->ops);
    $register = conditionsOf($this, $id);
    expect($register['status'])->toBe('conditions_precedent')
        ->and(collect($register['conditions'])->map(fn ($c) => [$c['kind'], $c['source'], $c['mandatory'], $c['status']])->all())
        ->toBe([['precedent', 'approval', true, 'open'], ['subsequent', 'approval', true, 'open']])
        ->and($register['readiness']['ready_for_release'])->toBeFalse();

    // a further CP the legal team needs, with owner and due date
    $insurance = $this->api('POST', "/api/v1/applications/{$id}/conditions", [
        'kind' => 'precedent', 'description' => 'Credit life insurance policy assigned to the bank', 'mandatory' => true, 'owner_id' => $this->ops->id, 'due_date' => now()->addDays(7)->toDateString(),
    ])->assertCreated()->json('data');
    expect($insurance)->toMatchArray(['source' => 'manual', 'status' => 'open', 'is_overdue' => false])->and($insurance['owner']['id'])->toBe($this->ops->id);

    // check with a CP open: recorded, failed on CPs only
    $first = runPreDisbursementCheck($this, $id);
    $byCode = collect($first['items'])->keyBy('code');
    expect($first['passed'])->toBeFalse()
        ->and($byCode['conditions_precedent']['status'])->toBe('fail')
        ->and($byCode['conditions_precedent']['detail'])->toContain('deed of guarantee')->toContain('insurance')
        ->and($byCode['kyc_current']['status'])->toBe('pass')
        ->and($byCode['screening_clear']['status'])->toBe('pass')
        ->and($byCode['documentation_executed']['status'])->toBe('pass')
        ->and($byCode['approval_valid']['status'])->toBe('pass')
        ->and($byCode['account_verified']['status'])->toBe('pass')
        ->and($byCode['security_perfected']['status'])->toBe('not_applicable')
        ->and($first['destination'])->toMatchArray(['bank_code' => '058', 'account' => '******6789', 'account_name' => 'NWOSU AMAKA', 'name_match_score' => 100])
        ->and($first['screening']['runs'])->toBe(2);
    expect(app(ConditionsGate::class)->releaseBlockers($id))->toHaveCount(3)
        ->and(app(ConditionsGate::class)->verifiedDestination($id))->toBeNull();

    // satisfy the guarantee CP with evidence; evidence is required
    $guarantee = collect(conditionsOf($this, $id)['conditions'])->firstWhere('kind', 'precedent');
    $this->api('POST', "/api/v1/conditions/{$guarantee['id']}/actions/satisfy", ['note' => 'Deed received and stamped'])->assertStatus(422);
    $done = $this->api('POST', "/api/v1/conditions/{$guarantee['id']}/actions/satisfy", ['note' => 'Deed received and stamped', 'file' => UploadedFile::fake()->createWithContent('deed.pdf', "%PDF-1.4\nDeed\n%%EOF\n")])->assertOk()->json('data');
    expect($done['status'])->toBe('satisfied')->and($done['evidence']['version_id'])->not->toBeNull()->and($done['signoff']['by']['id'])->toBe($this->ops->id);
    expect(DB::table('documents')->where('application_id', $id)->where('document_type', 'CONDITION_EVIDENCE')->exists())->toBeTrue();
    expect(offerAppStatusOf($id))->toBe('conditions_precedent');

    // the insurance CP is waived through maker-checker; the maker cannot check it
    $cr = $this->api('POST', "/api/v1/conditions/{$insurance['id']}/actions/request-waiver", ['reason' => 'Policy in force under the group scheme; assignment follows'])->assertStatus(202)->json('data');
    expect(collect(conditionsOf($this, $id)['conditions'])->firstWhere('id', $insurance['id'])['status'])->toBe('waiver_pending');
    $this->api('POST', "/api/v1/change-requests/{$cr['id']}/actions/approve")->assertForbidden();
    $this->login($this->checker);
    $this->api('POST', "/api/v1/change-requests/{$cr['id']}/actions/approve")->assertOk();
    expect(offerAppStatusOf($id))->toBe('ready_for_disbursement');

    // now the final check passes and the gate releases the verified destination
    $this->login($this->ops);
    $second = runPreDisbursementCheck($this, $id);
    expect($second)->toMatchArray(['passed' => true, 'sequence' => 2, 'is_current' => true]);
    $register = conditionsOf($this, $id);
    expect($register['readiness'])->toBe(['ready_for_release' => true, 'blockers' => []]);
    $destination = app(ConditionsGate::class)->verifiedDestination($id);
    expect($destination->accountNo)->toBe('0123456789')->and($destination->bankCode)->toBe('058')->and($destination->checkId)->toBe($second['id']);
    // the account number is stored encrypted; checks are evidence
    expect(DB::table('pre_disbursement_checks')->where('id', $second['id'])->value('account_no_enc'))->not->toContain('0123456789');
    expect(fn () => DB::transaction(fn () => DB::table('pre_disbursement_checks')->where('id', $first['id'])->update(['passed' => true])))->toThrow(QueryException::class);
    expect(DB::table('audit_events')->where('action', 'condition.waived')->where('entity_id', $insurance['id'])->exists())->toBeTrue();

    // a check goes stale
    $this->travel(25)->hours();
    expect(app(ConditionsGate::class)->releaseBlockers($id)[0])->toContain('out of date');
})->group('FR-CPR-001', 'FR-CPR-002', 'FR-CPR-003', 'FR-CPR-005');

it('fails the check on a name mismatch or an unknown account (FR-CPR-005)', function () {
    $case = acceptedCase($this);
    $this->login($this->ops);
    $mismatch = collect(runPreDisbursementCheck($this, $case['app']['id'], '0123456790')['items'])->firstWhere('code', 'account_verified');
    expect($mismatch['status'])->toBe('fail')->and($mismatch['detail'])->toContain('JOHN DOE TRADING')->toContain('does not match');
    $unknown = collect(runPreDisbursementCheck($this, $case['app']['id'], '0000000000')['items'])->firstWhere('code', 'account_verified');
    expect($unknown['status'])->toBe('fail')->and($unknown['detail'])->toContain('rejected');
    $this->api('POST', "/api/v1/applications/{$case['app']['id']}/pre-disbursement-checks", ['bank_code' => '058', 'account_no' => '12345'])->assertStatus(422);
})->group('FR-CPR-005');

it('re-screens before disbursement and blocks on a hit (FR-CPR-004)', function () {
    // the guarantor is on the synthetic PEP register; intake screening was skipped in this setup, so the hit appears now
    $case = acceptedCase($this, 'Emeka', 'Obi');
    $this->login($this->ops);
    $result = runPreDisbursementCheck($this, $case['app']['id']);
    $screening = collect($result['items'])->firstWhere('code', 'screening_clear');
    expect($result['passed'])->toBeFalse()
        ->and($screening['status'])->toBe('fail')
        ->and($result['screening'])->toMatchArray(['new_hits' => 1, 'open_alerts' => 1])
        ->and(DB::table('screening_runs')->where('application_id', $case['app']['id'])->where('trigger', 'pre_disbursement')->count())->toBe(2)
        ->and(DB::table('screening_alerts')->where('application_id', $case['app']['id'])->where('status', 'open')->count())->toBe(1);
})->group('FR-CPR-004');

it('refuses condition work outside the window and without the permission', function () {
    $case = acceptedCase($this);
    $this->login($this->rm);
    $this->api('POST', "/api/v1/applications/{$case['app']['id']}/conditions", ['kind' => 'precedent', 'description' => 'Something else'])->assertForbidden();
    $this->login($this->ops);
    $this->api('POST', "/api/v1/applications/{$case['app']['id']}/conditions", ['kind' => 'precedent', 'description' => 'Board resolution', 'owner_id' => '01a00000-0000-7000-8000-000000000000'])
        ->assertStatus(422)->assertJsonValidationErrors(['owner_id']);
})->group('FR-CPR-001');

function offerAppStatusOf(string $applicationId): string
{
    return (string) DB::table('applications')->where('id', $applicationId)->value('canonical_status');
}

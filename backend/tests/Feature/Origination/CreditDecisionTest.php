<?php

declare(strict_types=1);

use Fundly\Modules\Access\Domain\Permission;
use Fundly\Modules\Application\Contracts\ApplicationLifecycle;
use Fundly\Modules\Application\Contracts\CanonicalStatus;
use Fundly\Modules\Credit\Contracts\CreditFile;
use Fundly\Shared\Security\Principal;
use Fundly\Shared\Security\SystemIdentity;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\LendingFixtures;

beforeEach(function () {
    $this->tenant = $this->provisionTenant();
    LendingFixtures::bindSimulators();
    $this->org = LendingFixtures::orgTree($this);
    LendingFixtures::activateProduct($this, 'sme-term-loan', 'SME Term Loan', LendingFixtures::smeTermLoan());
    $this->policy = LendingFixtures::activateConfig($this, 'credit.rule_set', 'sme-policy', 'SME credit policy', LendingFixtures::smePolicy());
    $this->rm = $this->userWith([Permission::ApplicationView, Permission::ApplicationOriginate, Permission::PartyManage]);
    $this->analyst = $this->userWith([Permission::ApplicationView, Permission::CreditAnalyse, Permission::BureauPull, Permission::ApplicationRecommend, Permission::ExceptionRaise]);
});

/** A company application moved to Assessment (KYC and documents are covered elsewhere). */
function assessmentCase(object $t, string $rc = 'RC1234567', string $income = '3500000.00', string $amount = '12500000.00', int $years = 9): array
{
    $t->login($t->rm);
    $company = LendingFixtures::company($t, 'Case '.$rc.' Limited', $rc, $t->org['lagos']['id']);
    LendingFixtures::consent($t, $company, ['data_processing']);
    $app = $t->api('POST', '/api/v1/applications', [
        'legal_entity_id' => $t->org['le']['id'], 'org_unit_id' => $t->org['lagos']['id'], 'product_key' => 'sme-term-loan', 'primary_party_id' => $company['id'],
        'requested_amount' => $amount, 'tenor_months' => 24, 'purpose' => 'Equipment', 'data' => ['monthly_income' => $income, 'years_trading' => $years],
    ])->assertCreated()->json('data');
    $etag = $t->api('GET', "/api/v1/applications/{$app['id']}")->headers->get('ETag');
    $t->api('POST', "/api/v1/applications/{$app['id']}/actions/submit", [], ['If-Match' => $etag])->assertOk();
    $system = Principal::system($t->currentTenantFixture()->id, SystemIdentity::Workflow);
    foreach ([CanonicalStatus::Documentation, CanonicalStatus::Assessment] as $to) {
        app(ApplicationLifecycle::class)->advance($app['id'], $to, 'TEST_SETUP', null, $system);
    }

    return ['app' => $app, 'company' => $company];
}

function decide(object $t, array $case): array
{
    $t->login($t->analyst);
    $t->api('POST', "/api/v1/applications/{$case['app']['id']}/bureau-reports/actions/pull")->assertCreated();

    return $t->api('POST', "/api/v1/applications/{$case['app']['id']}/decisions/actions/run")->assertCreated()->json('data');
}

it('validates rule sets: known facts, whitelisted functions, reason codes and no auto-decline (D-038b)', function () {
    $author = $this->userWith([Permission::ConfigRead, Permission::ConfigAuthor]);
    $this->login($author);
    $artifact = $this->api('POST', '/api/v1/config-artifacts/credit.rule_set', ['key' => 'bad', 'name' => 'Bad'])->assertCreated()->json('data');
    $bad = LendingFixtures::smePolicy([
        'evaluator_version' => '9.9.9',
        'formulas' => [['name' => 'x', 'expression' => 'shell_exec("id")'], ['name' => 'y', 'expression' => 'salary.amount * 2']],
        'policy' => ['rows' => [['when' => 'bureau.score < 500', 'outputs' => ['outcome' => 'decline']]]],
        'affordability' => ['max_dsr_percent' => '140', 'dsr_formula' => 'nope'],
    ]);
    $errors = $this->api('POST', "/api/v1/config-artifacts/credit.rule_set/{$artifact['id']}/versions", ['content' => $bad])->assertStatus(422)->json('errors');
    expect(array_keys($errors))->toContain('content.evaluator_version', 'content.formulas.0.expression', 'content.formulas.1.expression', 'content.policy.rows.0.outputs.outcome', 'content.affordability.max_dsr_percent', 'content.affordability.dsr_formula')
        ->and($errors['content.policy.rows.0.outputs.outcome'][0])->toContain('D-038b');
})->group('FR-CRD-001', 'FR-CRD-002', 'FR-CFG-002');

it('blocks the bureau pull without consent, then parses the report into the canonical profile (demo step 9)', function () {
    $case = assessmentCase($this);
    $this->login($this->analyst);
    $this->api('POST', "/api/v1/applications/{$case['app']['id']}/bureau-reports/actions/pull")->assertStatus(422)->assertJsonPath('code', 'bureau-consent-missing');
    $this->login($this->rm);
    LendingFixtures::consent($this, $case['company'], ['credit_bureau']);
    $this->login($this->analyst);
    $report = $this->api('POST', "/api/v1/applications/{$case['app']['id']}/bureau-reports/actions/pull")->assertCreated()->json('data');
    expect($report)->toMatchArray(['bureau' => 'Simulated Credit Bureau', 'hit' => true, 'is_valid' => true])
        ->and($report['profile']['score'])->toBeInt()->toBeGreaterThanOrEqual(600)
        ->and($report['profile']['active_facilities'])->toBe(count($report['profile']['facilities']))
        ->and($report['profile']['monthly_obligations']['currency'])->toBe('NGN');
    // the integration call log never carries the RC number in clear
    expect(DB::table('integration_calls')->where('port', 'credit_bureau')->value('request'))->not->toContain('RC1234567');
})->group('FR-CRD-003', 'FR-CRD-004', 'FR-CMP-020', 'FR-CUS-007');

it('requires Assessment and a current bureau report before a decision runs', function () {
    $case = assessmentCase($this);
    $this->login($this->analyst);
    $this->api('POST', "/api/v1/applications/{$case['app']['id']}/decisions/actions/run")->assertStatus(422)->assertJsonPath('detail', 'Pull a current credit bureau report for the primary applicant before running the decision (FR-CMP-020).');
    $this->login($this->rm);
    $this->api('POST', "/api/v1/applications/{$case['app']['id']}/decisions/actions/run")->assertForbidden();
})->group('FR-CMP-020');

it('approves a clean, affordable case with grade, pricing, affordability trace and ordered reason codes', function () {
    $case = assessmentCase($this);
    $this->login($this->rm);
    LendingFixtures::consent($this, $case['company'], ['credit_bureau']);
    $d = decide($this, $case);
    expect($d['outcome'])->toBe('approve')
        ->and($d['risk_grade'])->toBeIn(['A', 'B', 'C'])
        ->and(collect($d['reason_codes'])->pluck('code')->last())->toBe('OUT_APPROVE')
        ->and($d['affordability']['passed'])->toBeTrue()
        ->and((float) $d['affordability']['dsr_percent'])->toBeLessThanOrEqual(40.0)
        ->and($d['rule_set'])->toMatchArray(['key' => 'sme-policy', 'version_id' => $this->policy['version']['id']])
        ->and($d['evaluator_version'])->toBe('1.0.0')
        ->and(collect($d['trace'])->pluck('stage')->unique()->values()->all())->toBe(['formula', 'knockout', 'policy', 'grade', 'affordability', 'pricing', 'outcome'])
        ->and($d['facts']['bureau']['report_id'])->toBe($d['bureau_report_id']);
})->group('FR-CRD-008', 'FR-CRD-010', 'FR-CMP-027', 'FR-CMP-043', 'LOS-CON-006');

it('refers (never declines) on knock-outs and policy hits, and counter-offers when only affordability fails', function () {
    $writeOff = assessmentCase($this, 'RC5550013');
    $this->login($this->rm);
    LendingFixtures::consent($this, $writeOff['company'], ['credit_bureau']);
    $d = decide($this, $writeOff);
    expect($d['outcome'])->toBe('refer')->and(collect($d['reason_codes'])->pluck('code')->all())->toContain('KO_WRITE_OFF', 'POL_DPD_30', 'POL_LOW_SCORE', 'OUT_REFER');

    $stretched = assessmentCase($this, 'RC5550021', income: '1200000.00', amount: '20000000.00');
    $this->login($this->rm);
    LendingFixtures::consent($this, $stretched['company'], ['credit_bureau']);
    $c = decide($this, $stretched);
    expect($c['outcome'])->toBe('counter_offer')
        ->and($c['affordability']['passed'])->toBeFalse()
        ->and(collect($c['reason_codes'])->pluck('code')->all())->toContain('AFF_DSR_EXCEEDED', 'OUT_COUNTER_OFFER')
        ->and((float) $c['recommended_terms']['amount']['amount'])->toBeLessThan(20000000.0)->toBeGreaterThanOrEqual(500000.0)
        ->and(fmod((float) $c['recommended_terms']['amount']['amount'], 10000.0))->toBe(0.0);
})->group('FR-CRD-010', 'FR-CRD-011', 'FR-CMP-043');

it('stores immutable snapshots that replay identically, even after a newer rule set is activated (demo step 12)', function () {
    $case = assessmentCase($this);
    $this->login($this->rm);
    LendingFixtures::consent($this, $case['company'], ['credit_bureau']);
    $d = decide($this, $case);
    expect(fn () => DB::transaction(fn () => DB::table('decision_snapshots')->where('id', $d['id'])->update(['outcome' => 'decline'])))->toThrow(QueryException::class);

    LendingFixtures::activateConfig($this, 'credit.rule_set', 'sme-policy', 'SME credit policy', LendingFixtures::smePolicy(['affordability' => ['max_dsr_percent' => '5']]));
    $this->login($this->analyst);
    $replay = $this->api('POST', "/api/v1/decisions/{$d['id']}/actions/replay")->assertOk()->json('data');
    expect($replay)->toMatchArray(['identical' => true, 'evaluator_version' => '1.0.0', 'rule_set_version_id' => $this->policy['version']['id'], 'differences' => []]);
    // a new run picks up the new version and decides differently
    $again = $this->api('POST', "/api/v1/applications/{$case['app']['id']}/decisions/actions/run")->assertCreated()->json('data');
    expect($again['sequence'])->toBe(2)->and($again['rule_set']['version_no'])->toBe(2)->and($again['outcome'])->not->toBe('approve');
    expect(collect($this->api('GET', "/api/v1/applications/{$case['app']['id']}/decisions")->json('data'))->pluck('is_latest')->all())->toBe([true, false]);
})->group('FR-CRD-014', 'LOS-CON-006');

it('records overrides against the decision\'s own reason codes, writes a versioned memo, and only then allows recommend (demo steps 10-11)', function () {
    $case = assessmentCase($this, 'RC5550077'); // many enquiries → policy refer
    $this->login($this->rm);
    LendingFixtures::consent($this, $case['company'], ['credit_bureau']);
    $d = decide($this, $case);
    expect($d['outcome'])->toBe('refer');

    $etag = fn () => $this->api('GET', "/api/v1/applications/{$case['app']['id']}")->headers->get('ETag');
    $blocked = $this->api('POST', "/api/v1/applications/{$case['app']['id']}/actions/recommend", [], ['If-Match' => $etag()])->assertStatus(422)->json('blockers');
    expect($blocked)->toBe(['Write the credit memo on the latest decision.']);

    $this->api('POST', "/api/v1/decisions/{$d['id']}/exceptions", ['reason_code' => 'NOT_A_CODE', 'justification' => 'Enquiries relate to a single refinancing exercise in March.', 'severity' => 'medium'])->assertStatus(422);
    $ex = $this->api('POST', "/api/v1/decisions/{$d['id']}/exceptions", ['reason_code' => 'POL_ENQUIRIES', 'justification' => 'Enquiries relate to a single refinancing exercise in March.', 'evidence_ref' => 'DMS-9910', 'severity' => 'medium'])->assertCreated()->json('data');
    expect($ex)->toMatchArray(['reason_code' => 'POL_ENQUIRIES', 'severity' => 'medium', 'raised_by' => $this->analyst->id]);

    $memo = $this->api('GET', "/api/v1/applications/{$case['app']['id']}/credit-memo")->assertOk()->json('data');
    expect($memo['latest'])->toBeNull()->and(collect($memo['draft_sections'])->pluck('key')->all())->toBe(['applicants', 'facility', 'bureau', 'decision', 'affordability', 'exceptions']);
    $v1 = $this->api('POST', "/api/v1/applications/{$case['app']['id']}/credit-memo", ['narrative' => 'Strong trading history; enquiries explained by a refinancing exercise.', 'recommendation' => 'approve', 'conditions' => ['Personal guarantee of the MD']])->assertCreated()->json('data');
    $v2 = $this->api('POST', "/api/v1/applications/{$case['app']['id']}/credit-memo", ['narrative' => 'Updated after site visit: operations as described, stock verified.', 'recommendation' => 'approve', 'conditions' => ['Personal guarantee of the MD', 'Quarterly management accounts']])->assertCreated()->json('data');
    expect([$v1['version_no'], $v2['version_no']])->toBe([1, 2])
        ->and(collect($v2['sections'])->firstWhere('key', 'exceptions')['content']['items'][0]['reason_code'])->toBe('POL_ENQUIRIES');

    $done = $this->api('POST', "/api/v1/applications/{$case['app']['id']}/actions/recommend", [], ['If-Match' => $etag()])->assertOk()->json('data');
    expect($done['status'])->toBe('recommended');
    $credit = app(CreditFile::class)->latestDecision($case['app']['id']);
    expect($credit?->exceptionCount)->toBe(1)->and($credit?->maxExceptionSeverity)->toBe('medium')->and($credit?->memoRecommendation)->toBe('approve');
})->group('FR-CRD-013', 'FR-CRD-015', 'FR-CMP-027');

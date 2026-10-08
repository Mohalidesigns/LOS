<?php

declare(strict_types=1);

use Fundly\Modules\Access\Domain\Permission;
use Fundly\Modules\Application\Contracts\CanonicalStatus;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Support\LendingFixtures;

beforeEach(function () {
    $this->tenant = $this->provisionTenant();
    $this->org = LendingFixtures::orgTree($this);
    $this->product = LendingFixtures::activateProduct($this, 'sme-term-loan', 'SME Term Loan', LendingFixtures::smeTermLoan());
    $this->rm = $this->userWith([Permission::ApplicationView, Permission::ApplicationOriginate, Permission::PartyManage]);
    $this->login($this->rm);
    $this->company = LendingFixtures::company($this, orgUnitId: $this->org['lagos']['id']);
});

function newApplication(object $t, array $overrides = []): TestResponse
{
    return $t->api('POST', '/api/v1/applications', array_merge([
        'legal_entity_id' => $t->org['le']['id'], 'org_unit_id' => $t->org['lagos']['id'], 'product_key' => 'sme-term-loan',
        'primary_party_id' => $t->company['id'], 'requested_amount' => '12500000.00', 'tenor_months' => 24, 'purpose' => 'Purchase of processing equipment',
        'data' => ['sector' => 'agro_processing', 'years_trading' => 9],
    ], $overrides));
}

function act(object $t, array $app, string $action, array $body = []): TestResponse
{
    $etag = $t->api('GET', "/api/v1/applications/{$app['id']}")->headers->get('ETag');

    return $t->api('POST', "/api/v1/applications/{$app['id']}/actions/{$action}", $body, ['If-Match' => (string) $etag]);
}

it('creates a draft with a gap-free human reference, pinned product version and channel attribution', function () {
    $res = newApplication($this)->assertCreated();
    $app = $res->json('data');
    expect($app['reference'])->toBe('DEMO-'.now('Africa/Lagos')->format('Y').'-000001')
        ->and($app['status'])->toBe('draft')
        ->and($app['product']['version_id'])->toBe($this->product['version']['id'])
        ->and($app['channel'])->toBe('staff')
        ->and($app['originator_id'])->toBe($this->rm->id)
        ->and($app['requested_amount'])->toBe(['amount' => '12500000.0000', 'currency' => 'NGN'])
        ->and($app['applicants'])->toBe([['party_id' => $this->company['id'], 'role' => 'primary', 'party_type' => 'limited_company', 'display_name' => 'Adebayo Foods Limited']])
        ->and($app['completeness']['percent'])->toBe(100)
        ->and(array_column($app['completeness']['checklist'], 'code'))->toBe(['CAC_CERT', 'STATEMENT_6M', 'AUDITED_FS'])
        ->and($res->headers->get('ETag'))->not->toBeEmpty();
    expect(newApplication($this)->json('data.reference'))->toEndWith('-000002');
    // a failed creation does not burn a number (gap-free)
    newApplication($this, ['product_key' => 'nope'])->assertStatus(422);
    expect(newApplication($this)->json('data.reference'))->toEndWith('-000003');
})->group('FR-APP-001', 'FR-CHN-002', 'FR-PRD-006');

it('records field provenance for captured and amended data', function () {
    $app = newApplication($this)->json('data');
    $paths = DB::table('field_provenance')->where('application_id', $app['id'])->where('version', 1)->pluck('source', 'field_path')->all();
    expect($paths)->toMatchArray(['requested_amount' => 'staff', 'tenor_months' => 'staff', 'purpose' => 'staff', 'data.sector' => 'staff', 'data.years_trading' => 'staff']);
    $etag = $this->api('GET', "/api/v1/applications/{$app['id']}")->headers->get('ETag');
    $this->api('PATCH', "/api/v1/applications/{$app['id']}", ['requested_amount' => '15000000.00', 'source' => 'cba', 'source_ref' => 'CBA-CUST-889'], ['If-Match' => $etag])->assertOk();
    $row = DB::table('field_provenance')->where('application_id', $app['id'])->where('field_path', 'requested_amount')->orderByDesc('version')->first();
    expect($row->source)->toBe('cba')->and($row->source_ref)->toBe('CBA-CUST-889')->and((int) $row->version)->toBe(2);
})->group('LOS-FR-301');

it('amends before approval with If-Match and keeps field-level history', function () {
    $app = newApplication($this)->json('data');
    $this->api('PATCH', "/api/v1/applications/{$app['id']}", ['tenor_months' => 30])->assertStatus(428);
    $this->api('PATCH', "/api/v1/applications/{$app['id']}", ['tenor_months' => 30], ['If-Match' => '"stale"'])->assertStatus(412);
    $etag = $this->api('GET', "/api/v1/applications/{$app['id']}")->headers->get('ETag');
    $updated = $this->api('PATCH', "/api/v1/applications/{$app['id']}", ['tenor_months' => 30, 'data' => ['years_trading' => 10]], ['If-Match' => $etag])->assertOk();
    expect($updated->json('data.tenor_months'))->toBe(30)->and($updated->json('data.data.years_trading'))->toBe(10)->and($updated->json('data.version'))->toBe(2);
    // the old ETag is now stale
    $this->api('PATCH', "/api/v1/applications/{$app['id']}", ['tenor_months' => 12], ['If-Match' => $etag])->assertStatus(412);
    $amended = collect($this->api('GET', "/api/v1/applications/{$app['id']}/timeline")->json('data'))->firstWhere('type', 'application.amended');
    expect($amended['payload']['changes'])->toEqualCanonicalizing(['tenor_months' => ['from' => 24, 'to' => 30], 'data.years_trading' => ['from' => 9, 'to' => 10]]);
    expect(DB::table('audit_events')->where('entity_id', $app['id'])->where('action', 'application.amended')->exists())->toBeTrue();
})->group('FR-APP-005');

it('submits only within the pinned product limits and with required data', function () {
    $app = newApplication($this, ['requested_amount' => '90000000.00', 'purpose' => null])->json('data');
    $res = act($this, $app, 'submit')->assertStatus(422);
    expect($res->json('blockers'))->toContain('purpose is required.', 'requested_amount must be between 500000.0000 and 50000000.0000 NGN.');
    $etag = $this->api('GET', "/api/v1/applications/{$app['id']}")->headers->get('ETag');
    $this->api('PATCH', "/api/v1/applications/{$app['id']}", ['requested_amount' => '20000000.00', 'purpose' => 'Expansion'], ['If-Match' => $etag])->assertOk();
    $submitted = act($this, $app, 'submit')->assertOk()->json('data');
    expect($submitted['status'])->toBe('submitted')->and($submitted['submitted_at'])->not->toBeNull();
    $audit = DB::table('audit_events')->where('entity_id', $app['id'])->where('action', 'application.status_changed')->first();
    expect(json_decode($audit->after, true))->toMatchArray(['status' => 'submitted', 'action' => 'submit']);
})->group('FR-PRD-003', 'FR-APP-008', 'LOS-FR-282');

it('adds and removes guarantors and joint applicants', function () {
    $app = newApplication($this)->json('data');
    $guarantor = LendingFixtures::person($this, 'Ngozi', 'Okafor', '22345678991');
    $etag = $this->api('GET', "/api/v1/applications/{$app['id']}")->headers->get('ETag');
    $with = $this->api('POST', "/api/v1/applications/{$app['id']}/applicants", ['party_id' => $guarantor['id'], 'role' => 'guarantor'], ['If-Match' => $etag])->assertCreated()->json('data');
    expect(collect($with['applicants'])->pluck('role', 'display_name')->all())->toBe(['Adebayo Foods Limited' => 'primary', 'Ngozi Okafor' => 'guarantor'])
        ->and(array_column($with['completeness']['checklist'], 'code'))->toContain('GOVT_ID');
    $etag = $this->api('GET', "/api/v1/applications/{$app['id']}")->headers->get('ETag');
    $this->api('DELETE', "/api/v1/applications/{$app['id']}/applicants/{$this->company['id']}", [], ['If-Match' => $etag])->assertStatus(422);
    $without = $this->api('DELETE', "/api/v1/applications/{$app['id']}/applicants/{$guarantor['id']}", [], ['If-Match' => $etag])->assertOk()->json('data');
    expect($without['applicants'])->toHaveCount(1);
})->group('FR-APP-004');

it('runs hold/resume, rework and terminal actions with mandatory reasons and permission per action', function () {
    $app = newApplication($this)->json('data');
    act($this, $app, 'submit')->assertOk();
    // intake automation (Workflow) pre-qualifies and moves the case to KycScreening
    expect($this->api('GET', "/api/v1/applications/{$app['id']}")->json('data.status'))->toBe(CanonicalStatus::KycScreening->value);

    act($this, $app, 'hold')->assertStatus(422)->assertJsonPath('code', 'application-rule-violation');
    act($this, $app, 'hold', ['reason_code' => 'AWAITING_CUSTOMER', 'reason_text' => 'Director abroad'])->assertOk()->assertJsonPath('data.status', 'on_hold')->assertJsonPath('data.resume_to', 'kyc_screening');
    act($this, $app, 'resume')->assertOk()->assertJsonPath('data.status', 'kyc_screening');
    // return/recommend need application:recommend, which the RM lacks
    act($this, $app, 'return', ['reason_code' => 'MISSING_INFO', 'return_to' => 'draft'])->assertForbidden();
    $analyst = $this->userWith([Permission::ApplicationView, Permission::ApplicationRecommend]);
    $this->login($analyst);
    act($this, $app, 'return', ['reason_code' => 'MISSING_INFO', 'return_to' => 'documentation'])->assertStatus(422);
    act($this, $app, 'return', ['reason_code' => 'MISSING_INFO', 'return_to' => 'draft'])->assertOk()->assertJsonPath('data.status', 'returned_for_rework');
    $this->login($this->rm);
    act($this, $app, 'resubmit')->assertOk()->assertJsonPath('data.status', 'draft');
    act($this, $app, 'withdraw', ['reason_code' => 'CUSTOMER_DECLINED'])->assertOk()->assertJsonPath('data.status', 'withdrawn')->assertJsonPath('data.close_reason_code', 'CUSTOMER_DECLINED');
    act($this, $app, 'cancel', ['reason_code' => 'X'])->assertStatus(422);
    $statuses = collect($this->api('GET', "/api/v1/applications/{$app['id']}/timeline")->json('data'))->where('type', 'application.status_changed')->pluck('payload.to')->all();
    expect($statuses)->toBe(['submitted', 'pre_qualified', 'kyc_screening', 'on_hold', 'kyc_screening', 'returned_for_rework', 'draft', 'withdrawn']);
})->group('LOS-FR-283', 'FR-APP-006', 'FR-WFL-007', 'FR-WFL-008');

it('keeps the event store append-only and the projection rebuildable', function () {
    $app = newApplication($this)->json('data');
    act($this, $app, 'submit')->assertOk();
    // savepoints keep the test transaction usable after each rejected statement
    expect(fn () => DB::transaction(fn () => DB::table('application_events')->where('application_id', $app['id'])->update(['type' => 'tampered'])))->toThrow(QueryException::class);
    expect(fn () => DB::transaction(fn () => DB::table('application_events')->where('application_id', $app['id'])->delete()))->toThrow(QueryException::class);
    expect(fn () => DB::transaction(fn () => DB::table('field_provenance')->where('application_id', $app['id'])->delete()))->toThrow(QueryException::class);
    expect(DB::table('application_events')->where('application_id', $app['id'])->orderBy('version')->pluck('type')->all())->toBe(['application.created', 'application.status_changed', 'application.status_changed', 'application.status_changed']);
})->group('FR-AUD-003');

it('reconstructs the state at an instant and verifies the projection against events', function () {
    $app = newApplication($this)->json('data');
    $this->travel(5)->minutes();
    $mid = now()->toIso8601ZuluString();
    $this->travel(5)->minutes();
    act($this, $app, 'submit')->assertOk();
    $before = $this->api('GET', "/api/v1/applications/{$app['id']}/as-at?t=".urlencode($mid))->assertOk()->json('data.state');
    expect($before['status'])->toBe('draft')->and($before['version'])->toBe(1);
    expect($this->api('GET', "/api/v1/applications/{$app['id']}/as-at?t=".urlencode(now()->addMinute()->toIso8601ZuluString()))->json('data.state.status'))->toBe('kyc_screening');
    expect(Artisan::call('applications:verify-projection'))->toBe(0);
})->group('FR-AUD-010');

it('scopes lists, stats and reads to the branch subtree and denies other branches', function () {
    newApplication($this)->assertCreated();
    $kanoCo = LendingFixtures::company($this, 'Kano Grains Limited', 'RC5550001', $this->org['kano']['id']);
    newApplication($this, ['org_unit_id' => $this->org['kano']['id'], 'primary_party_id' => $kanoCo['id']])->assertCreated();
    $kanoApp = $this->api('GET', '/api/v1/applications?filter[q]=Kano')->json('data.0');

    $lagosManager = $this->userWith([Permission::ApplicationView, Permission::ApplicationOriginate], ['org_unit_id' => $this->org['lagos']['id']]);
    $this->login($lagosManager);
    $names = collect($this->api('GET', '/api/v1/applications')->json('data'))->pluck('primary_applicant.display_name')->all();
    expect($names)->toBe(['Adebayo Foods Limited']);
    expect($this->api('GET', '/api/v1/applications/stats')->json('data'))->toBe(['by_status' => ['draft' => 1], 'open' => 1, 'total' => 1]);
    $this->api('GET', "/api/v1/applications/{$kanoApp['id']}")->assertForbidden();
    // cannot originate into another branch
    newApplication($this, ['org_unit_id' => $this->org['kano']['id'], 'primary_party_id' => $kanoCo['id']])->assertForbidden();
})->group('FR-SEC-003', 'FR-RPT-010');

it('rejects products that do not serve the applicant type and unknown branches', function () {
    LendingFixtures::activateProduct($this, 'personal-loan', 'Personal Loan', LendingFixtures::smeTermLoan(['category' => 'personal', 'segment' => 'retail', 'applicant_types' => ['individual']]));
    $this->login($this->rm);
    newApplication($this, ['product_key' => 'personal-loan'])->assertStatus(422)->assertJsonStructure(['errors' => ['primary_party_id']]);
    $this->api('POST', '/api/v1/org-units', [])->assertForbidden();
})->group('FR-CUS-001');

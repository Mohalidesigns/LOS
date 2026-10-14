<?php

declare(strict_types=1);

use Fundly\Modules\Access\Domain\Permission;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\Journey;
use Tests\Support\LendingFixtures;

beforeEach(function () {
    $this->j = Journey::setUp($this);
    // the SME product now asks for security: cover ≥ 120%, LTV ≤ 80%, stamping may follow booking (G-34)
    LendingFixtures::activateProduct($this, 'sme-term-loan', 'SME Term Loan', LendingFixtures::smeTermLoan([
        'collateral' => ['required' => true, 'min_cover_percent' => '120', 'max_ltv_percent' => '80', 'perfection_timing' => ['stamping' => 'subsequent']],
    ]));
    $this->officer = $this->userWith([Permission::ApplicationView, Permission::CollateralManage], roleCode: 'collateral_officer');
    $this->waiver = $this->userWith([Permission::ApplicationView, Permission::ConditionWaiveApprove, Permission::ChangeRequestRead]);
});

function securityFile(object $t, string $applicationId): array
{
    return $t->api('GET', "/api/v1/applications/{$applicationId}/collateral")->assertOk()->json('data');
}

function captureCollateral(object $t, string $applicationId, array $fields): array
{
    $t->login($t->officer);

    return $t->api('POST', '/api/v1/collaterals', ['application_id' => $applicationId] + $fields)->assertCreated()->json('data');
}

it('computes cover against the product, blocks disbursement until perfection and insurance are met or waived, and hands the security over (FR-COL-001/002/004/006/008)', function () {
    $app = $this->j->approved();
    $borrower = $app['primary_applicant']['party_id'];
    $house = captureCollateral($this, $app['id'], ['type_code' => 'real_estate', 'description' => 'Duplex, 4 Bode Thomas St, Surulere', 'market_value' => '6000000.00', 'owner_party_id' => $borrower, 'state_code' => 'LA']);
    expect($house)->toMatchArray(['haircut_percent' => '30.0000', 'value_basis' => 'declared', 'state_code' => 'LA'])
        ->and($house['owner']['name'])->toBe('Amaka Nwosu')
        ->and($house['forced_sale_value'])->toBe(['amount' => '4200000.0000', 'currency' => 'NGN'])
        ->and($house['allocation']['total']['amount'])->toBe('4200000.0000')
        ->and($house['insurance'])->toMatchArray(['requirement' => 'required', 'status' => 'missing'])
        ->and(collect($house['perfection']['steps'])->mapWithKeys(fn ($s) => [$s['code'] => $s['timing']])->all())
        ->toBe(['title_search' => 'precedent', 'mortgage_executed' => 'precedent', 'stamping' => 'subsequent', 'consent' => 'subsequent', 'registration' => 'subsequent']);

    // cover = 4.2m / 3m, LTV = 3m / 6m, against the product's thresholds
    $file = securityFile($this, $app['id']);
    expect($file['cover'])->toMatchArray(['cover_percent' => '140.00', 'ltv_percent' => '50.00', 'meets_cover' => true, 'meets_ltv' => true])
        ->and($file['policy'])->toBe(['required' => true, 'min_cover_percent' => '120', 'max_ltv_percent' => '80'])
        ->and($file['security'])->toMatchArray(['applicable' => true, 'perfected' => false, 'insurance_in_force' => false, 'open_subsequent_steps' => 3])
        ->and($file['security']['blockers'])->toHaveCount(3);

    // approvers see the security in the packet
    $this->login($this->j->approver);
    $request = $this->api('GET', "/api/v1/applications/{$app['id']}/approval-requests")->json('data.0');
    $packet = $this->api('GET', "/api/v1/approval-requests/{$request['id']}")->assertOk()->json('data.packet.collateral');
    expect($packet)->toMatchArray(['cover_percent' => '140.00', 'ltv_percent' => '50.00', 'outstanding_perfection' => 2])
        ->and($packet['items'][0]['description'])->toBe('Duplex, 4 Bode Thomas St, Surulere');

    // the final check fails on security until it is perfected and insured
    $check = $this->j->acceptAndCheck($app, $this->j->issueOffer($app), expectPass: false);
    $items = collect($check['items'])->keyBy('code');
    expect($items['security_perfected']['status'])->toBe('fail')
        ->and($items['security_perfected']['detail'])->toContain('Search at the Lands Registry')->toContain('Deed of legal mortgage executed')
        ->and($items['insurance_in_force']['status'])->toBe('fail');

    // perfection: evidence is required; one step is completed, the other waived through maker-checker
    $this->login($this->officer);
    $steps = collect($house['perfection']['steps'])->keyBy('code');
    $this->api('PATCH', "/api/v1/perfection-steps/{$steps['title_search']['id']}", ['responsible_user_id' => $this->officer->id, 'start' => true])->assertOk();
    $this->api('POST', "/api/v1/perfection-steps/{$steps['title_search']['id']}/actions/complete", ['note' => 'Search clean'])->assertStatus(422);
    $done = $this->api('POST', "/api/v1/perfection-steps/{$steps['title_search']['id']}/actions/complete", ['note' => 'Search clean', 'file' => UploadedFile::fake()->createWithContent('search.pdf', "%PDF-1.4\nSearch\n%%EOF\n")])->assertOk()->json('data');
    expect(collect($done['perfection']['steps'])->firstWhere('code', 'title_search'))->toMatchArray(['status' => 'completed'])
        ->and(DB::table('documents')->where('application_id', $app['id'])->where('document_type', 'PERFECTION_EVIDENCE')->exists())->toBeTrue();
    $cr = $this->api('POST', "/api/v1/perfection-steps/{$steps['mortgage_executed']['id']}/actions/request-waiver", ['reason' => 'Deed executed in escrow; release on the day of disbursement'])->assertStatus(202)->json('data');
    $this->api('POST', "/api/v1/change-requests/{$cr['id']}/actions/approve")->assertForbidden();
    expect(securityFile($this, $app['id'])['security']['blockers'][0])->toContain('waiver awaiting a checker');
    $this->login($this->waiver);
    $this->api('POST', "/api/v1/change-requests/{$cr['id']}/actions/approve")->assertOk();

    // insurance: a policy without the bank's interest noted does not count
    $this->login($this->officer);
    $policy = ['policy_no' => 'LEAD/FIRE/2026/0041', 'insurer' => 'Leadway Assurance', 'sum_insured' => '6000000.00', 'start_date' => now()->toDateString(), 'expiry_date' => now()->addYear()->toDateString()];
    $plain = $this->api('POST', "/api/v1/collaterals/{$house['id']}/insurance-policies", $policy + ['bank_interest_noted' => false])->assertCreated()->json('data');
    expect($plain['insurance']['status'])->toBe('interest_not_noted');
    $noted = $this->api('POST', "/api/v1/collaterals/{$house['id']}/insurance-policies", ['policy_no' => 'LEAD/FIRE/2026/0041-E1'] + $policy + ['bank_interest_noted' => true])->assertCreated()->json('data');
    expect($noted['insurance']['status'])->toBe('in_force')->and($noted['insurance']['policies'][0]['under_insured'])->toBeFalse();
    expect(securityFile($this, $app['id'])['security'])->toMatchArray(['perfected' => true, 'insurance_in_force' => true, 'blockers' => []]);

    // the check now passes; subsequent steps follow the loan
    $this->login($this->j->ops);
    $pass = $this->api('POST', "/api/v1/applications/{$app['id']}/pre-disbursement-checks", ['bank_code' => '058', 'account_no' => '0123456789'])->assertCreated()->json('data');
    $items = collect($pass['items'])->keyBy('code');
    expect($pass['passed'])->toBeTrue()
        ->and($items['security_perfected']['detail'])->toContain('3 step(s) follow as conditions subsequent')
        ->and($items['insurance_in_force']['status'])->toBe('pass');
    $booked = $this->j->book($app);
    expect($booked['status'])->toBe('booked');

    // FR-HND-003: open perfection items and the insurance expiry go to servicing
    $this->login($this->j->ops);
    $content = $this->api('GET', "/api/v1/applications/{$app['id']}/handover")->assertOk()->json('data.package.content');
    expect($content['package_version'])->toBe(2)
        ->and($content['collateral'])->toHaveCount(1)
        ->and(collect($content['collateral'][0]['open_perfection_steps'])->pluck('code')->all())->toBe(['stamping', 'consent', 'registration'])
        ->and($content['collateral'][0]['insurance'])->toMatchArray(['policy_no' => 'LEAD/FIRE/2026/0041-E1', 'bank_interest_noted' => true]);

    // conditions subsequent are still worked after booking
    $this->login($this->officer);
    $this->api('POST', "/api/v1/perfection-steps/{$steps['stamping']['id']}/actions/complete", ['note' => 'Stamped', 'evidence_reference' => 'FIRS-SD-2026-778812'])->assertOk();
    expect(DB::table('audit_events')->whereIn('action', ['collateral.created', 'collateral.perfection_waived', 'collateral.insured', 'collateral.perfection_completed'])->distinct()->count('action'))->toBe(4);
})->group('FR-COL-001', 'FR-COL-002', 'FR-COL-004', 'FR-COL-006', 'FR-COL-008', 'FR-HND-003', 'FR-APV-010');

it('splits one item across facilities without over-allocating, re-values it, and prompts before the valuation lapses (FR-COL-003/005)', function () {
    $a = $this->j->approved();
    $car = captureCollateral($this, $a['id'], ['type_code' => 'vehicle', 'description' => 'Toyota Hilux 2023, LND-482-KJ', 'market_value' => '2000000.00', 'owner_name' => 'Adebayo Foods Ltd']);
    expect($car['forced_sale_value']['amount'])->toBe('1200000.0000')->and($car['valuation'])->toMatchArray(['required' => true, 'status' => 'none']);

    // a second facility for a related borrower (third-party security)
    $this->login($this->j->rm);
    $p = LendingFixtures::person($this, 'Tunde', 'Bakare', '22211122233', $this->j->org['lagos']['id']);
    $b = $this->api('POST', '/api/v1/applications', [
        'legal_entity_id' => $this->j->org['le']['id'], 'org_unit_id' => $this->j->org['lagos']['id'], 'product_key' => 'sme-term-loan', 'primary_party_id' => $p['id'],
        'requested_amount' => '1000000.00', 'tenor_months' => 12, 'purpose' => 'Working capital',
    ])->assertCreated()->json('data');

    $this->login($this->officer);
    $this->api('POST', "/api/v1/collaterals/{$car['id']}/allocations", ['application_id' => $b['id'], 'amount' => '500000.00'])->assertStatus(422)->assertJsonValidationErrors(['amount']);
    $this->api('POST', "/api/v1/collaterals/{$car['id']}/allocations", ['application_id' => $a['id'], 'amount' => '700000.00'])->assertOk();
    $split = $this->api('POST', "/api/v1/collaterals/{$car['id']}/allocations", ['application_id' => $b['id'], 'amount' => '500000.00'])->assertOk()->json('data');
    expect($split['allocation'])->toMatchArray(['over_allocated' => false])
        ->and($split['allocation']['available']['amount'])->toBe('0.0000')
        ->and(collect($split['allocation']['items'])->pluck('application_reference')->all())->toBe([$a['reference'], $b['reference']]);
    $this->api('POST', "/api/v1/collaterals/{$car['id']}/allocations", ['application_id' => $b['id'], 'amount' => '600000.00'])->assertStatus(422);
    expect($this->api('GET', "/api/v1/collaterals?filter[application_id]={$b['id']}")->assertOk()->json('data'))->toHaveCount(1);

    // a lower valuation: the forced-sale value follows the haircut, cover is scaled pro rata
    $valued = $this->api('POST', "/api/v1/collaterals/{$car['id']}/valuations", [
        'valuer' => 'Knight Frank Nigeria', 'valuation_date' => now()->toDateString(), 'method' => 'market_comparison', 'market_value' => '1500000.00',
        'file' => UploadedFile::fake()->createWithContent('valuation.pdf', "%PDF-1.4\nValuation\n%%EOF\n"),
    ])->assertCreated()->json('data');
    expect($valued)->toMatchArray(['value_basis' => 'valuation'])
        ->and($valued['forced_sale_value']['amount'])->toBe('900000.0000')
        ->and($valued['allocation']['over_allocated'])->toBeTrue()
        ->and($valued['valuation'])->toMatchArray(['status' => 'current', 'valid_until' => now()->addMonthsNoOverflow(12)->toDateString()])
        ->and($valued['valuation']['history'][0]['report'])->not->toBeNull();
    // 700k × 900k / 1.2m = 525k against 3m
    expect(securityFile($this, $a['id'])['cover'])->toMatchArray(['cover_percent' => '17.50', 'meets_cover' => false]);
    $this->api('POST', "/api/v1/collaterals/{$car['id']}/valuations", ['valuer' => 'X', 'valuation_date' => now()->addDay()->toDateString(), 'method' => 'desktop', 'market_value' => '1.00'])->assertStatus(422);

    // re-valuation prompt to collateral officers inside the notice window, once
    Artisan::call('notifications:reminders');
    expect(DB::table('communications')->where('event', 'collateral.revaluation_due')->count())->toBe(0);
    $this->travel(345)->days();
    Artisan::call('notifications:reminders');
    Artisan::call('notifications:reminders');
    $prompts = DB::table('communications')->where('event', 'collateral.revaluation_due')->get();
    expect($prompts->pluck('recipient_user_id')->all())->toBe([$this->officer->id])
        ->and($prompts->first()->subject)->toContain($a['reference']);
    $this->login($this->officer);
    expect(collect($this->api('GET', "/api/v1/collaterals/{$car['id']}")->json('data.valuation'))->get('status'))->toBe('due_soon');
})->group('FR-COL-003', 'FR-COL-005');

it('captures every configured type, validates tenant types, follows state checklists and enforces authority (FR-COL-001/004)', function () {
    $this->login($this->officer);
    $types = collect($this->api('GET', '/api/v1/collateral-types')->assertOk()->json('data'))->keyBy('code');
    expect($types->keys()->all())->toContain('real_estate', 'vehicle', 'plant_equipment', 'inventory', 'receivables', 'cash_deposit', 'quoted_securities', 'personal_guarantee', 'corporate_guarantee', 'institutional_guarantee', 'insurance_assignment');

    $app = $this->j->approved();
    $this->login($this->officer);
    $this->api('POST', '/api/v1/collaterals', ['application_id' => $app['id'], 'type_code' => 'spaceship', 'description' => 'Starship', 'market_value' => '1.00', 'owner_name' => 'X'])->assertStatus(422)->assertJsonValidationErrors(['type_code']);
    $this->api('POST', '/api/v1/collaterals', ['application_id' => $app['id'], 'type_code' => 'cash_deposit', 'description' => 'Fixed deposit', 'market_value' => '1000000.00'])->assertStatus(422)->assertJsonValidationErrors(['owner_name']);
    $abuja = captureCollateral($this, $app['id'], ['type_code' => 'real_estate', 'description' => 'Plot 12, Maitama', 'market_value' => '9000000.00', 'owner_name' => 'Amaka Nwosu', 'state_code' => 'FC']);
    expect(collect($abuja['perfection']['steps'])->firstWhere('code', 'consent')['name'])->toBe("Minister's consent (FCT, AGIS)");

    // a tenant's own type, through maker-checker; bad content is refused
    $author = $this->userWith([Permission::ConfigRead, Permission::ConfigAuthor]);
    $this->login($author);
    $artifact = $this->api('POST', '/api/v1/config-artifacts/collateral.type', ['key' => 'tractor', 'name' => 'Tractor'])->assertCreated()->json('data');
    $errors = $this->api('POST', "/api/v1/config-artifacts/collateral.type/{$artifact['id']}/versions", ['content' => [
        'name' => 'Tractor', 'category' => 'farm', 'haircut_percent' => '140', 'requires_valuation' => true, 'requires_insurance' => 'yes',
        'perfection' => ['default' => [['code' => 'NCR', 'name' => 'NCR', 'timing' => 'later']]],
    ]])->assertStatus(422)->json('errors');
    expect(array_keys($errors))->toContain('content.category', 'content.haircut_percent', 'content.requires_insurance', 'content.perfection.default.0.code', 'content.perfection.default.0.timing');
    $version = LendingFixtures::activateConfig($this, 'collateral.type', 'tractor', 'Tractor', [
        'name' => 'Tractor (agricultural)', 'category' => 'equipment', 'haircut_percent' => '45', 'requires_valuation' => true, 'valuation_validity_months' => 12, 'requires_insurance' => false,
        'perfection' => ['default' => [['code' => 'ncr_registration', 'name' => 'Registered on the NCR', 'timing' => 'precedent', 'due_days' => 7]]],
    ]);
    $tractor = captureCollateral($this, $app['id'], ['type_code' => 'tractor', 'description' => 'John Deere 5075E', 'market_value' => '4000000.00', 'owner_name' => 'Amaka Nwosu']);
    expect($tractor['type'])->toMatchArray(['code' => 'tractor', 'name' => 'Tractor (agricultural)', 'source' => 'tenant', 'version_id' => $version['version']['id']])
        ->and($tractor['forced_sale_value']['amount'])->toBe('2200000.0000')
        ->and($tractor['perfection']['steps'][0]['due_date'])->toBe(now()->addDays(7)->toDateString());

    // only collateral officers capture security
    $this->login($this->j->rm);
    $this->api('POST', '/api/v1/collaterals', ['application_id' => $app['id'], 'type_code' => 'cash_deposit', 'description' => 'Fixed deposit', 'market_value' => '1000000.00', 'owner_name' => 'Amaka'])->assertForbidden();
    $this->api('GET', "/api/v1/applications/{$app['id']}/collateral")->assertOk();
})->group('FR-COL-001', 'FR-COL-004');

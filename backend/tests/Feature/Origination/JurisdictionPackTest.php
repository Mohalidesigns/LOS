<?php

declare(strict_types=1);

use Fundly\Modules\Access\Domain\Permission;
use Fundly\Modules\Compliance\Contracts\ComplianceGate;
use Fundly\Modules\Compliance\Domain\Packs\DefaultPacks;
use Illuminate\Support\Facades\DB;
use Tests\Support\Journey;
use Tests\Support\LendingFixtures;

beforeEach(function () {
    $this->j = Journey::setUp($this);
    $this->compliance = $this->userWith([Permission::ConfigRead, Permission::ApplicationView]);
});

/** The draft Nigeria pack with every rule verified (what P1-CMP-04 produces), bureau validity 45 days. */
function verifiedNigeriaPack(): array
{
    $pack = DefaultPacks::nigeriaCommercialBankV0();
    foreach ($pack['rules'] as &$r) {
        $r['verification_status'] = 'verified';
        $r['verified_by'] = 'Head of Compliance';
        $r['verified_on'] = '2026-10-01';
        $r['citation']['url'] = 'https://www.cbn.gov.ng/';
        $r['citation']['retrieved_on'] = '2026-10-01';
        if ($r['code'] === 'bureau_report_validity') {
            $r['parameters']['days'] = 45;
        }
    }
    $pack['name'] = 'Nigeria × commercial bank v1';

    return $pack;
}

function prudentialBase(string $amount, string $from = '2026-01-01'): array
{
    return ['entries' => [['effective_from' => $from, 'shareholders_funds_unimpaired' => ['amount' => $amount, 'currency' => 'NGN'], 'source' => 'Audited accounts FY2025']]];
}

it('activates a pack only when every rule is verified, resolves it by the booking entity, and shows the register (FR-CMP-001/003, TRD §9.4)', function () {
    $author = $this->userWith([Permission::ConfigRead, Permission::ConfigAuthor, Permission::ConfigActivateRequest]);
    $checker = $this->userWith([Permission::ConfigRead, Permission::ConfigReview, Permission::ConfigActivateApprove]);
    $this->login($author);
    $defaults = $this->api('GET', '/api/v1/jurisdiction-packs/defaults')->assertOk()->json('data');
    expect($defaults[0]['key'])->toBe('ng.commercial_bank')
        ->and(collect($defaults[0]['content']['rules'])->pluck('verification_status')->unique()->all())->toBe(['unverified']);

    // content is validated: citations, parameters, dates
    $artifact = $this->api('POST', '/api/v1/config-artifacts/jurisdiction.pack', ['key' => 'ng.commercial_bank', 'name' => 'Nigeria commercial bank'])->assertCreated()->json('data');
    $bad = $defaults[0]['content'];
    $bad['rules'][0]['parameters']['limit_percent'] = '120';
    unset($bad['rules'][1]['citation']['instrument']);
    $bad['rules'][] = $bad['rules'][2];
    $errors = $this->api('POST', "/api/v1/config-artifacts/jurisdiction.pack/{$artifact['id']}/versions", ['content' => $bad])->assertStatus(422)->json('errors');
    expect(array_keys($errors))->toContain('content.rules.0.parameters.limit_percent', 'content.rules.1.citation.instrument', 'content.rules.8.effective_from');

    // the unverified draft can be authored and reviewed, but not put live
    $v = $this->api('POST', "/api/v1/config-artifacts/jurisdiction.pack/{$artifact['id']}/versions", ['content' => $defaults[0]['content']])->assertCreated()->json('data');
    $this->api('POST', "/api/v1/config-artifacts/jurisdiction.pack/{$artifact['id']}/versions/{$v['id']}/actions/submit")->assertOk();
    $this->login($checker);
    $this->api('POST', "/api/v1/config-artifacts/jurisdiction.pack/{$artifact['id']}/versions/{$v['id']}/actions/approve")->assertOk();
    $this->login($author);
    $refused = $this->api('POST', "/api/v1/config-artifacts/jurisdiction.pack/{$artifact['id']}/versions/{$v['id']}/actions/activate")->assertStatus(422)->json();
    expect(json_encode($refused))->toContain('single_obligor_limit')->toContain('verified');

    // verified: live, and the register shows it for the booking entity
    $active = LendingFixtures::activateConfig($this, 'jurisdiction.pack', 'ng.commercial_bank', 'Nigeria commercial bank', verifiedNigeriaPack());
    LendingFixtures::activateConfig($this, 'prudential.parameters', 'demo', 'Demo Bank prudential parameters', prudentialBase('250000000000.00'));
    $this->login($this->compliance);
    $register = $this->api('GET', "/api/v1/legal-entities/{$this->j->org['le']['id']}/regulatory-rules")->assertOk()->json('data');
    expect($register['pack'])->toMatchArray(['key' => 'ng.commercial_bank', 'version_id' => $active['version']['id'], 'unverified_rules' => 0])
        ->and($register['prudential']['shareholders_funds_unimpaired'])->toBe(['amount' => '250000000000.0000', 'currency' => 'NGN'])
        ->and(collect($register['rules'])->firstWhere('code', 'single_obligor_limit'))->toMatchArray(['kind' => 'parameter', 'parameters' => ['limit_percent' => '20', 'base' => 'shareholders_funds_unimpaired'], 'in_force' => true]);
    $this->login($this->j->rm);
    $this->api('GET', "/api/v1/legal-entities/{$this->j->org['le']['id']}/regulatory-rules")->assertForbidden();
})->group('FR-CMP-001', 'FR-CMP-003');

it('refuses approval over the single-obligor limit, records each check with its rule and base, and re-reads exposure from the core banking system (FR-CMP-023)', function () {
    LendingFixtures::activateConfig($this, 'jurisdiction.pack', 'ng.commercial_bank', 'Nigeria commercial bank', verifiedNigeriaPack());
    // base 10m → limit 2m, below the 3m requested
    LendingFixtures::activateConfig($this, 'prudential.parameters', 'demo', 'Demo Bank prudential parameters', prudentialBase('10000000.00'));
    $app = $this->j->approved(vote: false);
    // the pack sets the bureau validity window (45 days)
    expect(substr((string) DB::table('bureau_reports')->where('application_id', $app['id'])->value('valid_until'), 0, 10))->toBe(now()->addDays(45)->toDateString());

    $this->login($this->j->approver);
    $request = $this->api('GET', "/api/v1/applications/{$app['id']}/approval-requests")->json('data.0');
    $vote = fn () => $this->api('POST', "/api/v1/approval-requests/{$request['id']}/actions/vote", ['decision' => 'approve', 'rationale' => 'Affordable and within appetite.']);
    expect(json_encode($vote()->assertStatus(422)->json()))->toContain('single-obligor limit')->toContain('FR-CMP-023');

    // the base is revised (new effective-dated entry): 20m → limit 4m
    LendingFixtures::activateConfig($this, 'prudential.parameters', 'demo', 'Demo Bank prudential parameters', ['entries' => [
        prudentialBase('10000000.00')['entries'][0],
        ['effective_from' => now()->toDateString(), 'shareholders_funds_unimpaired' => ['amount' => '20000000.00', 'currency' => 'NGN'], 'source' => 'Regulatory return Q3 2026'],
    ]]);
    $this->login($this->j->approver);
    $vote()->assertOk();
    $this->login($this->j->rm);
    $checks = $this->api('GET', "/api/v1/applications/{$app['id']}/regulatory-limit-checks")->assertOk()->json('data');
    expect($checks[0])->toMatchArray(['stage' => 'approval', 'outcome' => 'pass'])
        ->and($checks[0]['figures'])->toMatchArray(['limit' => ['amount' => '4000000.0000', 'currency' => 'NGN'], 'existing' => ['amount' => '0.0000', 'currency' => 'NGN'], 'total' => ['amount' => '3000000.0000', 'currency' => 'NGN']])
        ->and($checks[0]['rule'])->toMatchArray(['code' => 'single_obligor_limit', 'pack' => 'ng.commercial_bank', 'verification_status' => 'verified'])
        ->and($checks[0]['base'])->toMatchArray(['effective_from' => now()->toDateString(), 'source' => 'Regulatory return Q3 2026']);

    // the final check includes the limit
    $check = $this->j->acceptAndCheck($app, $this->j->issueOffer($app));
    expect(collect($check['items'])->firstWhere('code', 'regulatory_limits'))->toMatchArray(['status' => 'pass']);
    $this->j->book($app);

    // once booked, the loan is exposure in the core banking system: the same facility again would breach
    $again = app(ComplianceGate::class)->regulatoryLimits($app['id'], 'pre_disbursement', 'system:test');
    expect($again->outcome)->toBe('breach')->and($again->blocks())->toBeTrue();
    $row = DB::table('regulatory_limit_checks')->where('id', $again->checkId)->first();
    expect($row->existing_amount)->toBe('3000000.0000')->and(json_decode($row->facilities, true))->toHaveCount(1);

    // retention follows the pack
    $this->login($this->j->ops);
    expect($this->api('GET', "/api/v1/applications/{$app['id']}/handover")->json('data.retention.basis'))->toContain('pack ng.commercial_bank');
})->group('FR-CMP-023', 'FR-CMP-003');

it('is not applicable without an active pack or prudential parameters', function () {
    $app = $this->j->approved();
    $this->login($this->j->rm);
    $checks = $this->api('GET', "/api/v1/applications/{$app['id']}/regulatory-limit-checks")->json('data');
    expect($checks[0])->toMatchArray(['outcome' => 'not_applicable', 'figures' => null])->and($checks[0]['detail'])->toContain('No active jurisdiction pack');
})->group('FR-CMP-023');

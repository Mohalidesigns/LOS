<?php

declare(strict_types=1);

use Fundly\Modules\Access\Domain\Permission;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\LendingFixtures;

beforeEach(function () {
    $this->tenant = $this->provisionTenant();
    LendingFixtures::bindSimulators();
    $this->org = LendingFixtures::orgTree($this);
    LendingFixtures::activateProduct($this, 'sme-term-loan', 'SME Term Loan', LendingFixtures::smeTermLoan());
    $this->rm = $this->userWith([Permission::ApplicationView, Permission::ApplicationOriginate, Permission::PartyManage]);
    $this->officerA = $this->userWith([Permission::ApplicationView, Permission::ScreeningReview, Permission::ScreeningClear]);
    $this->officerB = $this->userWith([Permission::ApplicationView, Permission::ScreeningReview, Permission::ScreeningClear]);
    $this->login($this->rm);
});

/** Demo step 4: a limited company, two directors and a guarantor. */
function captureCase(object $t, string $secondDirector = 'Chidi Nwosu'): array
{
    $company = LendingFixtures::company($t, orgUnitId: $t->org['lagos']['id']);
    [$first, $last] = explode(' ', $secondDirector, 2);
    $d1 = LendingFixtures::person($t, 'Adewale', 'Adebayo', '22200000011');
    $d2 = LendingFixtures::person($t, $first, $last, '22200000012');
    $guarantor = LendingFixtures::person($t, 'Ngozi', 'Okafor', '22345678991');
    foreach ([$d1, $d2] as $d) {
        $t->api('POST', "/api/v1/parties/{$company['id']}/relationships", ['related_party_id' => $d['id'], 'role' => 'director'])->assertCreated();
    }
    $app = $t->api('POST', '/api/v1/applications', [
        'legal_entity_id' => $t->org['le']['id'], 'org_unit_id' => $t->org['lagos']['id'], 'product_key' => 'sme-term-loan',
        'primary_party_id' => $company['id'], 'requested_amount' => '12500000.00', 'tenor_months' => 24, 'purpose' => 'Equipment',
    ])->assertCreated()->json('data');
    $etag = $t->api('GET', "/api/v1/applications/{$app['id']}")->headers->get('ETag');
    $t->api('POST', "/api/v1/applications/{$app['id']}/applicants", ['party_id' => $guarantor['id'], 'role' => 'guarantor'], ['If-Match' => $etag])->assertCreated();

    return compact('company', 'd1', 'd2', 'guarantor', 'app');
}

/** Demo step 5: verify BVNs and capture consents. */
function completeKyc(object $t, array $c): void
{
    foreach (['d1', 'd2', 'guarantor'] as $k) {
        $t->api('POST', "/api/v1/parties/{$c[$k]['id']}/identities/bvn/actions/verify")->assertOk()->assertJsonPath('data.identities.0.verification_status', 'verified');
    }
    LendingFixtures::consent($t, $c['company']);
    LendingFixtures::consent($t, $c['guarantor']);
}

function submit(object $t, array $app): void
{
    $etag = $t->api('GET', "/api/v1/applications/{$app['id']}")->headers->get('ETag');
    $t->api('POST', "/api/v1/applications/{$app['id']}/actions/submit", [], ['If-Match' => $etag])->assertOk();
}

function status(object $t, array $app): string
{
    return (string) $t->api('GET', "/api/v1/applications/{$app['id']}")->json('data.status');
}

it('moves a clean case from submit through automated pre-qualification and intake screening to Documentation', function () {
    $c = captureCase($this);
    completeKyc($this, $c);
    submit($this, $c['app']);
    expect(status($this, $c['app']))->toBe('kyc_screening'); // pre-qualified automatically, screening queued
    $kyc = $this->api('GET', "/api/v1/applications/{$c['app']['id']}/kyc")->assertOk()->json('data');
    expect($kyc['outcome'])->toBe('pending')->and(collect($kyc['conditions'])->firstWhere('code', 'SCREENING_COMPLETE')['met'])->toBeFalse();

    expect(LendingFixtures::drainOutbox($this)['dispatched'])->toBe(1);
    expect(status($this, $c['app']))->toBe('documentation');
    $kyc = $this->api('GET', "/api/v1/applications/{$c['app']['id']}/kyc")->json('data');
    expect($kyc['outcome'])->toBe('clear')->and($kyc['risk'])->toBe('standard')
        ->and($kyc['screening_runs'])->toHaveCount(4)
        ->and(collect($kyc['screening_runs'])->pluck('list_version')->unique()->all())->toBe(['SIM-2026.10']);
    $statuses = collect($this->api('GET', "/api/v1/applications/{$c['app']['id']}/timeline")->json('data'))->where('type', 'application.status_changed')->map(fn ($e) => [$e['payload']['to'], $e['actor']['id']])->values()->all();
    expect($statuses)->toBe([['submitted', $this->rm->id], ['pre_qualified', 'system:workflow'], ['kyc_screening', 'system:workflow'], ['documentation', 'system:workflow']]);
})->group('FR-CUS-005', 'FR-CUS-007', 'FR-CMP-011', 'LOS-FR-282');

it('blocks at KycScreening on a PEP hit until two different officers clear it; the originator may not (demo steps 6-7)', function () {
    $c = captureCase($this, 'Emeka Obi');
    completeKyc($this, $c);
    submit($this, $c['app']);
    LendingFixtures::drainOutbox($this);
    expect(status($this, $c['app']))->toBe('kyc_screening');
    $alert = $this->api('GET', "/api/v1/applications/{$c['app']['id']}/kyc")->json('data.alerts.0');
    expect($alert)->toMatchArray(['category' => 'pep', 'status' => 'open', 'matched_name' => 'Emeka Obi', 'list_version' => 'SIM-2026.10', 'party_name' => 'Emeka Obi']);

    // the RM cannot clear it (no permission), and an originator with screening rights is refused too (FR-CMP-014)
    $this->api('POST', "/api/v1/screening-alerts/{$alert['id']}/actions/propose", ['decision' => 'clear', 'reason' => 'Different person: date of birth differs'])->assertForbidden();
    $originatorWithRights = $this->userWith([Permission::ApplicationView, Permission::ScreeningReview, Permission::ScreeningClear]);
    DB::table('applications')->where('id', $c['app']['id'])->update(['originator_id' => $originatorWithRights->id]);
    $this->login($originatorWithRights);
    $this->api('POST', "/api/v1/screening-alerts/{$alert['id']}/actions/propose", ['decision' => 'clear', 'reason' => 'Different person: date of birth differs'])->assertStatus(422);
    DB::table('applications')->where('id', $c['app']['id'])->update(['originator_id' => $this->rm->id]);

    $this->login($this->officerA);
    $this->api('POST', "/api/v1/screening-alerts/{$alert['id']}/actions/propose", ['decision' => 'clear', 'reason' => 'Different person: date of birth and middle name differ', 'evidence_ref' => 'DMS-4471'])
        ->assertOk()->assertJsonPath('data.status', 'pending_confirmation');
    $this->api('POST', "/api/v1/screening-alerts/{$alert['id']}/actions/confirm", ['reason' => 'I agree with the analysis'])->assertStatus(422); // same officer
    expect(status($this, $c['app']))->toBe('kyc_screening');

    $this->login($this->officerB);
    $done = $this->api('POST', "/api/v1/screening-alerts/{$alert['id']}/actions/confirm", ['reason' => 'Reviewed evidence DMS-4471; false positive'])->assertOk()->json('data');
    expect($done)->toMatchArray(['status' => 'cleared', 'proposed_by' => $this->officerA->id, 'confirmed_by' => $this->officerB->id, 'evidence_ref' => 'DMS-4471']);
    expect(status($this, $c['app']))->toBe('documentation');
    $audit = DB::table('audit_events')->where('entity_id', $alert['id'])->orderBy('seq')->pluck('action')->all();
    expect($audit)->toBe(['compliance.screening_alert.raised', 'compliance.screening_alert.proposed', 'compliance.screening_alert.confirmed']);
})->group('FR-CMP-013', 'FR-CMP-014', 'FR-CMP-017');

it('suppresses a previously cleared hit on re-screening and blocks on a confirmed sanctions match', function () {
    $c = captureCase($this, 'Emeka Obi');
    completeKyc($this, $c);
    submit($this, $c['app']);
    LendingFixtures::drainOutbox($this);
    $alert = $this->api('GET', "/api/v1/applications/{$c['app']['id']}/kyc")->json('data.alerts.0');
    $this->login($this->officerA);
    $this->api('POST', "/api/v1/screening-alerts/{$alert['id']}/actions/propose", ['decision' => 'clear', 'reason' => 'Different person: date of birth differs'])->assertOk();
    $this->login($this->officerB);
    $this->api('POST', "/api/v1/screening-alerts/{$alert['id']}/actions/confirm", ['reason' => 'Agreed, false positive'])->assertOk();

    $this->login($this->officerA);
    $after = $this->api('POST', "/api/v1/applications/{$c['app']['id']}/kyc/actions/rescreen")->assertOk()->json('data');
    expect(collect($after['alerts'])->pluck('status')->all())->toBe(['cleared', 'cleared'])
        ->and($after['alerts'][1]['confirmation_note'])->toContain('Suppressed');

    // a sanctioned company: true match confirmed → blocked
    $this->login($this->rm);
    $bad = LendingFixtures::company($this, 'Blackwater Commodities Limited', 'RC9990001', $this->org['lagos']['id']);
    $app = $this->api('POST', '/api/v1/applications', ['legal_entity_id' => $this->org['le']['id'], 'org_unit_id' => $this->org['lagos']['id'], 'product_key' => 'sme-term-loan', 'primary_party_id' => $bad['id'], 'requested_amount' => '1000000.00', 'tenor_months' => 12, 'purpose' => 'Stock'])->json('data');
    submit($this, $app);
    LendingFixtures::drainOutbox($this);
    $hit = collect($this->api('GET', "/api/v1/applications/{$app['id']}/kyc")->json('data.alerts'))->firstWhere('category', 'sanction');
    $this->login($this->officerA);
    $this->api('POST', "/api/v1/screening-alerts/{$hit['id']}/actions/propose", ['decision' => 'true_match', 'reason' => 'Name, RC number and address match the list entry'])->assertOk();
    $this->login($this->officerB);
    $this->api('POST', "/api/v1/screening-alerts/{$hit['id']}/actions/confirm", ['reason' => 'Confirmed true match'])->assertOk()->assertJsonPath('data.status', 'confirmed_match');
    $kyc = $this->api('GET', "/api/v1/applications/{$app['id']}/kyc")->json('data');
    expect($kyc['outcome'])->toBe('blocked')->and($kyc['status'])->toBe('kyc_screening');
})->group('FR-CMP-013', 'FR-CMP-011');

it('verifies identities through the port and records failures without exposing the number', function () {
    $this->login($this->rm);
    $mismatch = LendingFixtures::person($this, 'Bola', 'Ade', '22200000099');
    $missing = LendingFixtures::person($this, 'Kemi', 'Lawal', '22200000100');
    $res = $this->api('POST', "/api/v1/parties/{$mismatch['id']}/identities/bvn/actions/verify")->assertOk()->json('data.identities.0');
    expect($res)->toMatchArray(['verification_status' => 'failed', 'match_score' => '66.00']);
    expect($this->api('POST', "/api/v1/parties/{$missing['id']}/identities/bvn/actions/verify")->json('data.identities.0.verification_status'))->toBe('failed');
    $this->api('POST', "/api/v1/parties/{$missing['id']}/identities/nin/actions/verify")->assertStatus(422);
    $call = DB::table('integration_calls')->where('port', 'identity_verification')->orderByDesc('started_at')->first();
    expect($call->request ?? '')->not->toContain('22200000099')->not->toContain('22200000100');
    expect(DB::table('audit_events')->where('action', 'party.identity.verified')->get()->toJson())->not->toContain('22200000099');
})->group('FR-CUS-002', 'FR-CUS-003');

it('keeps granular consent with withdrawal and full history, and the gate follows it', function () {
    $c = captureCase($this);
    completeKyc($this, $c);
    $this->api('POST', "/api/v1/parties/{$c['guarantor']['id']}/consents", ['purpose' => 'data_processing', 'action' => 'grant', 'channel' => 'branch', 'terms_version' => 'v2'])->assertStatus(409);
    $state = $this->api('POST', "/api/v1/parties/{$c['guarantor']['id']}/consents", ['purpose' => 'data_processing', 'action' => 'withdraw', 'channel' => 'call_centre', 'terms_version' => 'T&C-2026.1'])->assertCreated()->json('data');
    expect($state['current']['data_processing']['status'])->toBe('withdrawn')->and($state['current']['credit_bureau']['status'])->toBe('granted')
        ->and(collect($state['history'])->where('purpose', 'data_processing')->pluck('action')->all())->toBe(['grant', 'withdraw']);
    expect(fn () => DB::transaction(fn () => DB::table('party_consents')->where('party_id', $c['guarantor']['id'])->delete()))->toThrow(QueryException::class);

    submit($this, $c['app']);
    LendingFixtures::drainOutbox($this);
    expect(status($this, $c['app']))->toBe('kyc_screening');
    $unmet = collect($this->api('GET', "/api/v1/applications/{$c['app']['id']}/kyc")->json('data.conditions'))->where('met', false)->pluck('detail')->all();
    expect($unmet)->toBe(['Ngozi Okafor: consent to data processing']);
    // re-granting consent re-evaluates the gate (PartyKycChanged) and the case moves on
    LendingFixtures::consent($this, $c['guarantor'], ['data_processing']);
    expect(status($this, $c['app']))->toBe('documentation');
})->group('FR-CUS-008', 'FR-CMP-010', 'FR-CMP-021', 'FR-CMP-032', 'FR-CUS-007');

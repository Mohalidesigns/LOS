<?php

declare(strict_types=1);

use Fundly\Modules\Access\Domain\Permission;
use Illuminate\Support\Facades\DB;
use Tests\Support\LendingFixtures;

beforeEach(function () {
    $this->tenant = $this->provisionTenant();
    $this->org = LendingFixtures::orgTree($this);
    $this->rm = $this->userWith([Permission::ApplicationView, Permission::PartyManage, Permission::ApplicationOriginate]);
    $this->login($this->rm);
});

it('creates individual and limited-company parties with distinct schemas', function () {
    $company = LendingFixtures::company($this, orgUnitId: $this->org['lagos']['id']);
    expect($company['type'])->toBe('limited_company')
        ->and($company['display_name'])->toBe('Adebayo Foods Limited')
        ->and($company['registration_number'])->toBe('RC1234567')
        ->and($company['first_name'])->toBeNull();
    $person = LendingFixtures::person($this, 'Ngozi', 'Okafor', '22345678991');
    expect($person['type'])->toBe('individual')->and($person['display_name'])->toBe('Ngozi Okafor');

    // individual needs names and date of birth; company needs a name and RC number
    $errors = $this->api('POST', '/api/v1/parties', ['type' => 'individual'])->assertStatus(422)->json('errors');
    expect($errors)->toHaveKeys(['first_name', 'last_name', 'date_of_birth']);
    $errors = $this->api('POST', '/api/v1/parties', ['type' => 'limited_company'])->assertStatus(422)->json('errors');
    expect($errors)->toHaveKeys(['company_name', 'registration_number']);
    $errors = $this->api('POST', '/api/v1/parties', ['type' => 'individual', 'first_name' => 'A', 'last_name' => 'B', 'date_of_birth' => '1990-01-01', 'identities' => [['type' => 'bvn', 'value' => '123']]])
        ->assertStatus(422)->json('errors');
    expect($errors['identities.0.value'][0])->toBe('A bvn must be 11 digits.');
})->group('FR-CUS-001');

it('encrypts identifiers and contact data at rest and masks them by default', function () {
    $person = LendingFixtures::person($this, 'Ngozi', 'Okafor', '22345678991');
    expect($person['identities'][0])->toMatchArray(['type' => 'bvn', 'value_masked' => '2234*****91', 'verification_status' => 'unverified'])
        ->and($person['phone_masked'])->toBe('+234********91')
        ->and($person['date_of_birth_masked'])->toBe('1980****17');
    $row = DB::table('parties')->where('id', $person['id'])->first();
    $identity = DB::table('party_identities')->where('party_id', $person['id'])->first();
    expect($row->phone_enc)->toStartWith('fe1.')->not->toContain('0802')
        ->and($row->date_of_birth_enc)->not->toContain('1980')
        ->and($identity->value_enc)->not->toContain('22345678991')
        ->and(json_encode($this->api('GET', "/api/v1/parties/{$person['id']}")->json()))->not->toContain('22345678991');
    // the audit trail never carries the clear value either
    expect(DB::table('audit_events')->where('entity_id', $person['id'])->get()->toJson())->not->toContain('22345678991');
})->group('FR-SEC-017', 'FR-CMP-036');

it('flags intake duplicates on identity, phone, registration number and fuzzy name', function () {
    $first = LendingFixtures::person($this, 'Ngozi', 'Okafor', '22345678991');
    $company = LendingFixtures::company($this);

    $second = $this->api('POST', '/api/v1/parties', [
        'type' => 'individual', 'first_name' => 'Okafor', 'last_name' => 'Ngozi', 'date_of_birth' => '1980-05-17',
        'identities' => [['type' => 'bvn', 'value' => '22345678991']],
    ])->assertCreated()->json('data');
    $dupe = collect($second['possible_duplicates'])->firstWhere('party_id', $first['id']);
    expect($dupe['matched_fields'])->toContain('bvn', 'name')->and((float) $dupe['name_similarity'])->toBeGreaterThan(0.9);

    $match = $this->api('POST', '/api/v1/parties/actions/match', ['registration_number' => 'rc1234567', 'name' => 'Adebayo Foods Nig Ltd'])->assertOk()->json('data');
    expect($match[0]['party_id'])->toBe($company['id'])->and($match[0]['matched_fields'])->toContain('registration_number', 'name');
    expect($this->api('POST', '/api/v1/parties/actions/match', ['phone' => '+2348031234567'])->json('data.0.party_id'))->toBe($company['id']);
    expect($this->api('POST', '/api/v1/parties/actions/match', ['name' => 'Completely Different Trading'])->json('data'))->toBe([]);
})->group('FR-CHN-007');

it('records directors and shareholders and computes look-through beneficial ownership', function () {
    $applicant = LendingFixtures::company($this);
    $holdco = LendingFixtures::company($this, 'Adebayo Holdings Limited', 'RC7654321');
    $ade = LendingFixtures::person($this, 'Adewale', 'Adebayo', '22200000001');
    $funke = LendingFixtures::person($this, 'Funke', 'Adebayo', '22200000002');

    $rel = fn (array $company, array $party, string $role, ?string $pct = null) => $this->api('POST', "/api/v1/parties/{$company['id']}/relationships", array_filter(['related_party_id' => $party['id'], 'role' => $role, 'ownership_percent' => $pct]));
    $rel($applicant, $ade, 'director')->assertCreated();
    $rel($applicant, $funke, 'director')->assertCreated();
    $rel($applicant, $ade, 'shareholder', '30')->assertCreated();
    $rel($applicant, $holdco, 'shareholder', '60')->assertCreated();
    $rel($holdco, $funke, 'shareholder', '75')->assertCreated();
    $rel($holdco, $ade, 'shareholder', '25')->assertCreated();

    // guards
    $rel($applicant, $funke, 'shareholder', '20')->assertStatus(422)->assertJsonPath('code', 'domain-rule-violation'); // 110%
    $rel($applicant, $holdco, 'director')->assertStatus(422); // a company cannot be a director
    $rel($applicant, $ade, 'director')->assertStatus(409);
    $rel($ade, $applicant, 'director')->assertStatus(422); // relationships hang off a company
    $rel($applicant, $funke, 'shareholder')->assertStatus(422); // percent required

    $view = $this->api('GET', "/api/v1/parties/{$applicant['id']}/relationships")->assertOk()->json('data');
    expect($view['relationships'])->toHaveCount(4);
    $owners = collect($view['beneficial_owners'])->pluck('effective_percent', 'display_name')->all();
    // Funke: 60% × 75% = 45%; Adewale: 30% direct + 60% × 25% = 45%
    expect($owners)->toEqualCanonicalizing(['Funke Adebayo' => '45.0000', 'Adewale Adebayo' => '45.0000']);
})->group('FR-CUS-006');

it('limits party lists and reads to the caller scope and denies writes without party:manage', function () {
    LendingFixtures::company($this, 'Lagos Co Limited', 'RC111', $this->org['lagos']['id']);
    $kanoCo = LendingFixtures::company($this, 'Kano Co Limited', 'RC222', $this->org['kano']['id']);
    $lagosOnly = $this->userWith([Permission::ApplicationView], ['org_unit_id' => $this->org['lagos']['id']]);
    $this->login($lagosOnly);
    expect(collect($this->api('GET', '/api/v1/parties')->json('data'))->pluck('display_name')->all())->toBe(['Lagos Co Limited']);
    $this->api('GET', "/api/v1/parties/{$kanoCo['id']}")->assertForbidden();
    $this->api('POST', '/api/v1/parties', ['type' => 'limited_company', 'company_name' => 'X', 'registration_number' => 'RC9'])->assertForbidden();
    expect(DB::table('audit_events')->where('action', 'authz.denied')->count())->toBeGreaterThan(0);
})->group('FR-SEC-003', 'FR-SEC-005');

<?php

declare(strict_types=1);

use Fundly\Modules\Access\Domain\Permission;
use Fundly\Modules\Product\Contracts\ProductCatalogue;
use Fundly\Shared\Money\Money;
use Tests\Support\LendingFixtures;

beforeEach(function () {
    $this->tenant = $this->provisionTenant();
});

it('defines a product through configuration, activated only via maker-checker, and exposes it to capture', function () {
    $rm = $this->userWith([Permission::ApplicationView]);
    $this->login($rm);
    expect($this->api('GET', '/api/v1/products')->assertOk()->json('data'))->toBe([]);

    $p = LendingFixtures::activateProduct($this, 'sme-term-loan', 'SME Term Loan', LendingFixtures::smeTermLoan());

    $this->login($rm);
    $list = $this->api('GET', '/api/v1/products')->assertOk()->json('data');
    expect($list)->toHaveCount(1)
        ->and($list[0]['key'])->toBe('sme-term-loan')
        ->and($list[0]['version_id'])->toBe($p['version']['id'])
        ->and($list[0]['amount']['max'])->toBe(['amount' => '50000000.0000', 'currency' => 'NGN'])
        ->and($list[0]['tenor_months'])->toBe(['min' => 3, 'max' => 36]);
    $detail = $this->api('GET', '/api/v1/products/sme-term-loan')->assertOk()->json('data');
    expect($detail['definition']['bindings'])->toEqualCanonicalizing(['workflow' => 'sme-standard', 'rule_set' => 'sme-policy', 'approval_matrix' => 'sme-matrix'])
        ->and($detail['definition']['fees'][0]['code'])->toBe('MGMT');
    $this->api('GET', '/api/v1/products/unknown')->assertNotFound();
})->group('FR-PRD-001', 'FR-PRD-003', 'FR-PRD-005');

it('validates product content: categories, ranges, interest basis, fees and checklist conditions', function () {
    $author = $this->userWith([Permission::ConfigRead, Permission::ConfigAuthor]);
    $this->login($author);
    $artifact = $this->api('POST', '/api/v1/config-artifacts/product', ['key' => 'bad', 'name' => 'Bad'])->assertCreated()->json('data');
    $bad = LendingFixtures::smeTermLoan([
        'category' => 'payday',
        'amount' => ['min' => '900.00', 'max' => '100.00'],
        'tenor_months' => ['min' => 40, 'max' => 12],
        'interest' => ['basis' => 'floating', 'rate_percent' => '120'],
        'fees' => [['code' => 'mgmt', 'name' => '', 'type' => 'monthly', 'calc' => 'percent', 'value' => '1.5']],
        'checklist' => [['code' => 'X', 'name' => 'X', 'mandatory' => 'yes', 'applies_to' => ['segments' => ['mega']]]],
    ]);
    $errors = $this->api('POST', "/api/v1/config-artifacts/product/{$artifact['id']}/versions", ['content' => $bad])->assertStatus(422)->json('errors');
    expect(array_keys($errors))->toContain('content.category', 'content.amount', 'content.tenor_months', 'content.interest.rate_percent', 'content.interest.index', 'content.fees.0.code', 'content.fees.0.name', 'content.fees.0.type', 'content.checklist.0.mandatory', 'content.checklist.0.applies_to.segments');
    // floats are rejected outright: money is a decimal string
    $errors = $this->api('POST', "/api/v1/config-artifacts/product/{$artifact['id']}/versions", ['content' => LendingFixtures::smeTermLoan(['amount' => ['min' => 1000.5, 'max' => '2000.00']])])->assertStatus(422)->json('errors');
    expect($errors)->toHaveKey('content.amount.min');
})->group('FR-PRD-002', 'FR-PRD-003', 'FR-PRD-004');

it('refuses salary-backed products until payroll mandates exist (D-038c)', function () {
    $author = $this->userWith([Permission::ConfigRead, Permission::ConfigAuthor]);
    $this->login($author);
    $artifact = $this->api('POST', '/api/v1/config-artifacts/product', ['key' => 'salary', 'name' => 'Salary advance'])->assertCreated()->json('data');
    $errors = $this->api('POST', "/api/v1/config-artifacts/product/{$artifact['id']}/versions", ['content' => LendingFixtures::smeTermLoan(['category' => 'salary_backed'])])
        ->assertStatus(422)->json('errors');
    expect($errors['content.category'][0])->toBe('Salary-backed products cannot be configured until payroll mandates are available (D-038c, FR-DSB-010).');
})->group('FR-PRD-002');

it('keeps in-flight work on the pinned version after a new version is activated', function () {
    $v1 = LendingFixtures::activateProduct($this, 'sme-term-loan', 'SME Term Loan', LendingFixtures::smeTermLoan());
    $v2 = LendingFixtures::activateProduct($this, 'sme-term-loan', 'SME Term Loan', LendingFixtures::smeTermLoan(['amount' => ['max' => '80000000.00']]));
    $catalogue = app(ProductCatalogue::class);
    expect($catalogue->activeByKey('sme-term-loan')?->versionId)->toBe($v2['version']['id'])
        ->and($catalogue->version($v1['version']['id'])?->amountMax->amount->__toString())->toBe('50000000.0000')
        ->and($catalogue->version($v1['version']['id'])?->status)->toBe('superseded');
})->group('FR-PRD-006');

it('filters the checklist by applicant type, segment and amount band', function () {
    LendingFixtures::activateProduct($this, 'sme-term-loan', 'SME Term Loan', LendingFixtures::smeTermLoan());
    $product = app(ProductCatalogue::class)->activeByKey('sme-term-loan');
    $small = array_column($product->checklistFor(['limited_company'], 'staff', Money::of('2000000', 'NGN')), 'code');
    $large = array_column($product->checklistFor(['limited_company', 'individual'], 'staff', Money::of('20000000', 'NGN')), 'code');
    expect($small)->toBe(['CAC_CERT', 'STATEMENT_6M'])
        ->and($large)->toBe(['CAC_CERT', 'STATEMENT_6M', 'AUDITED_FS', 'GOVT_ID']);
})->group('FR-PRD-004');

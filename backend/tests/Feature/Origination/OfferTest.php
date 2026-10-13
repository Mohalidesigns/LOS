<?php

declare(strict_types=1);

use Fundly\Modules\Access\Domain\Permission;
use Fundly\Modules\Application\Contracts\ApplicationLifecycle;
use Fundly\Modules\Application\Contracts\CanonicalStatus;
use Fundly\Modules\Offer\Contracts\OfferRegister;
use Fundly\Shared\Security\Principal;
use Fundly\Shared\Security\SystemIdentity;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\LendingFixtures;

beforeEach(function () {
    $this->tenant = $this->provisionTenant();
    LendingFixtures::bindSimulators();
    $this->org = LendingFixtures::orgTree($this);
    LendingFixtures::activateProduct($this, 'sme-term-loan', 'SME Term Loan', LendingFixtures::smeTermLoan());
    LendingFixtures::activateConfig($this, 'credit.rule_set', 'sme-policy', 'SME credit policy', LendingFixtures::smePolicy());
    LendingFixtures::activateConfig($this, 'approval.matrix', 'sme-matrix', 'SME approval matrix', LendingFixtures::smeMatrix());
    LendingFixtures::activateConfig($this, 'approval.authority_limits', 'default', 'Authority limits', LendingFixtures::authorityLimits());
    $this->template = LendingFixtures::activateConfig($this, 'offer.template', 'sme-offer', 'SME offer letter', LendingFixtures::offerTemplate());
    $this->rm = $this->userWith([Permission::ApplicationView, Permission::ApplicationOriginate, Permission::PartyManage]);
    $this->analyst = $this->userWith([Permission::ApplicationView, Permission::CreditAnalyse, Permission::BureauPull, Permission::ApplicationRecommend]);
    $this->approver = $this->userWith([Permission::ApplicationView, Permission::ApplicationApprove], roleCode: 'credit_approver');
    $this->officer = $this->userWith([Permission::ApplicationView, Permission::OfferPrepare]);
});

/**
 * Company + guarantor application, decided, recommended and voted on at L1.
 *
 * @return array{app: array<string, mixed>, company: array<string, mixed>, guarantor: array<string, mixed>}
 */
function offerReadyCase(object $t, string $decision = 'approve', array $vote = []): array
{
    $t->login($t->rm);
    $company = LendingFixtures::company($t, 'Adebayo Foods Limited', 'RC1234567', $t->org['lagos']['id']);
    LendingFixtures::consent($t, $company);
    $guarantor = LendingFixtures::person($t, 'Funke', 'Adebayo', '22212345678', $t->org['lagos']['id']);
    $app = $t->api('POST', '/api/v1/applications', [
        'legal_entity_id' => $t->org['le']['id'], 'org_unit_id' => $t->org['lagos']['id'], 'product_key' => 'sme-term-loan', 'primary_party_id' => $company['id'],
        'requested_amount' => '8000000.00', 'tenor_months' => 12, 'purpose' => 'Equipment', 'data' => ['monthly_income' => '9000000.00', 'years_trading' => 9],
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
    $t->api('POST', "/api/v1/applications/{$app['id']}/credit-memo", ['narrative' => 'Strong trading history and affordable repayments.', 'recommendation' => 'approve'])->assertCreated();
    $t->api('POST', "/api/v1/applications/{$app['id']}/actions/recommend", [], ['If-Match' => $etag()])->assertOk();
    $request = $t->api('GET', "/api/v1/applications/{$app['id']}/approval-requests")->json('data.0');
    $t->login($t->approver);
    $t->api('POST', "/api/v1/approval-requests/{$request['id']}/actions/vote", ['decision' => $decision, 'rationale' => 'Within appetite; cash flow covers the instalment.'] + $vote)->assertOk();

    return ['app' => $app, 'company' => $company, 'guarantor' => $guarantor];
}

function offerAppStatus(string $applicationId): string
{
    return (string) DB::table('applications')->where('id', $applicationId)->value('canonical_status');
}

function executedPdf(): UploadedFile
{
    return UploadedFile::fake()->createWithContent('executed.pdf', "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n");
}

it('validates offer templates: inert HTML, known placeholders, required blocks, wet-sign only (FR-OFR-001, LOS-FR-314)', function () {
    $author = $this->userWith([Permission::ConfigRead, Permission::ConfigAuthor]);
    $this->login($author);
    $artifact = $this->api('POST', '/api/v1/config-artifacts/offer.template', ['key' => 'bad', 'name' => 'Bad'])->assertCreated()->json('data');
    $bad = LendingFixtures::offerTemplate([
        'body_html' => '<script>x()</script><img src="https://evil.example/t.png">{{ applicant.salary }}',
        'execution_method' => 'e_sign',
        'signing_order' => ['guarantor', 'notary'],
    ]);
    $errors = $this->api('POST', "/api/v1/config-artifacts/offer.template/{$artifact['id']}/versions", ['content' => $bad])->assertStatus(422)->json('errors');
    expect(implode(' ', $errors['content.body_html']))->toContain('scripts')->toContain('applicant.salary')->toContain('{{ key_facts }}')
        ->and($errors['content.execution_method'][0])->toContain('P4')
        ->and($errors)->toHaveKey('content.signing_order');
})->group('FR-OFR-001', 'LOS-FR-314');

it('runs demo step 13: issues the offer with key facts, CBA schedule and PDF, delivers it, signs in order and stores the executed agreement', function () {
    $case = offerReadyCase($this, 'approve_with_conditions', ['reason_codes' => ['COND_GUARANTEE'], 'conditions' => [['kind' => 'precedent', 'description' => 'Personal guarantee of the MD']]]);
    $id = $case['app']['id'];
    expect(offerAppStatus($id))->toBe('approved');

    $this->login($this->rm);
    $this->api('POST', "/api/v1/applications/{$id}/offers")->assertForbidden();
    $this->login($this->officer);
    $offer = $this->api('POST', "/api/v1/applications/{$id}/offers")->assertCreated()->json('data');
    expect(offerAppStatus($id))->toBe('offer_issued')
        ->and($offer)->toMatchArray(['sequence' => 1, 'status' => 'issued', 'execution_method' => 'wet_sign', 'schedule_source' => 'core_banking:schedule.generate', 'reissue_count' => 0])
        ->and($offer['template'])->toMatchArray(['key' => 'sme-offer', 'version_id' => $this->template['version']['id']])
        ->and($offer['terms'])->toMatchArray(['amount' => ['amount' => '8000000.0000', 'currency' => 'NGN'], 'tenor_months' => 12, 'source' => 'approval'])
        ->and($offer['schedule'])->toHaveCount(12)
        ->and((float) end($offer['schedule'])['closing_balance'])->toBe(0.0)
        ->and(substr($offer['valid_until'], 0, 10))->toBe(now()->addDays(30)->toDateString());

    // key facts: fees 1% + 0.5% of 8M, cost of credit = interest + fees, APR above the nominal rate because fees come off the top
    $kf = $offer['key_facts'];
    $interest = array_sum(array_map(fn ($r) => (float) $r['interest'], $offer['schedule']));
    expect($kf)->toMatchArray(['method' => 'ng_v0', 'principal' => '8000000.00', 'total_fees' => '120000.00', 'net_disbursed' => '7880000.00', 'instalment_count' => 12, 'cooling_off_days' => 3])
        ->and((float) $kf['total_interest'])->toEqualWithDelta($interest, 0.001)
        ->and((float) $kf['total_cost_of_credit'])->toEqualWithDelta($interest + 120000, 0.001)
        ->and((float) $kf['apr_percent'])->toBeGreaterThan((float) $offer['terms']['rate_percent'])
        ->and((float) $kf['effective_annual_rate_percent'])->toBeGreaterThan((float) $kf['apr_percent']);

    // the PDF is in the vault, readable through the documents API, and carries the key facts and conditions
    $pdf = $this->get("http://{$this->tenant->host}/api/v1/documents/{$offer['document']['document_id']}/versions/{$offer['document']['version_id']}/content", ['Referer' => 'http://localhost/'])->assertOk();
    $bytes = $pdf->getContent();
    expect(hash('sha256', (string) $bytes))->toBe($offer['document']['sha256'])
        ->and((string) $bytes)->toStartWith('%PDF')->toContain('Total cost of credit')->toContain('Personal guarantee of the MD')->toContain('Adebayo Foods Limited');

    // delivery: email queued through the outbox, then sent with the PDF attached; a print copy is recorded
    $after = $this->api('POST', "/api/v1/offers/{$offer['id']}/deliveries", ['channel' => 'email'])->assertCreated()->json('data');
    expect($after['deliveries'][0])->toMatchArray(['channel' => 'email', 'status' => 'queued', 'recipient' => 'f******@adebayofoods.ng']);
    LendingFixtures::drainOutbox($this);
    expect(DB::table('offer_deliveries')->where('offer_id', $offer['id'])->value('status'))->toBe('sent');
    $this->api('POST', "/api/v1/offers/{$offer['id']}/deliveries", ['channel' => 'print'])->assertCreated()->assertJsonPath('data.deliveries.1.status', 'recorded');

    // signing order: company, guarantor, bank
    $signatories = $after['signatories'];
    expect(collect($signatories)->pluck('role')->all())->toBe(['primary', 'guarantor', 'bank'])
        ->and($signatories[0]['is_next'])->toBeTrue();
    $sign = fn (array $s) => $this->api('POST', "/api/v1/offers/{$offer['id']}/signatories/{$s['id']}/actions/sign", ['signed_at' => now()->toIso8601String(), 'witness_name' => 'Ade Witness', 'place' => 'Lagos']);
    $this->api('POST', "/api/v1/offers/{$offer['id']}/actions/accept", ['file' => executedPdf()])->assertStatus(422)->assertJsonPath('blockers.0', 'Adebayo Foods Limited');
    $sign($signatories[1])->assertStatus(422)->assertJsonPath('detail', fn ($d) => str_contains($d, 'Adebayo Foods Limited (position 1) signs first'));
    $sign($signatories[0])->assertOk();
    $sign($signatories[1])->assertOk();
    $sign($signatories[2])->assertOk()->assertJsonPath('data.signatories.2.signed', true);

    $accepted = $this->api('POST', "/api/v1/offers/{$offer['id']}/actions/accept", ['file' => executedPdf()])->assertOk()->json('data');
    expect($accepted['status'])->toBe('accepted')
        ->and($accepted['agreement'])->toMatchArray(['execution_method' => 'wet_sign', 'offer_document_sha256' => $offer['document']['sha256']])
        // acceptance opens the condition register at once (M7): the approver's CP is now tracked
        ->and(offerAppStatus($id))->toBe('conditions_precedent');
    $register = app(OfferRegister::class)->accepted($id);
    expect($register->agreementSha256)->toBe($accepted['agreement']['sha256'])
        ->and($register->schedule)->toHaveCount(12)
        ->and($register->templateVersionId)->toBe($this->template['version']['id']);
    // the executed agreement is evidence
    expect(fn () => DB::transaction(fn () => DB::table('agreements')->where('offer_id', $offer['id'])->update(['sha256' => str_repeat('0', 64)])))->toThrow(QueryException::class);
    expect(DB::table('audit_events')->where('entity_id', $offer['id'])->pluck('action')->all())->toContain('offer.issued', 'offer.delivered', 'offer.email_sent', 'offer.signature_recorded', 'offer.accepted');
})->group('FR-OFR-001', 'FR-OFR-002', 'FR-OFR-003', 'FR-OFR-004', 'FR-OFR-005', 'FR-OFR-009', 'FR-OFR-010', 'FR-CMP-025');

it('offers the counter-offer terms when the approver counter-offered', function () {
    $case = offerReadyCase($this, 'counter_offer', ['reason_codes' => ['CTR_AFFORDABILITY'], 'counter_terms' => ['amount' => '6000000.00', 'tenor_months' => 18]]);
    $this->login($this->officer);
    $offer = $this->api('POST', "/api/v1/applications/{$case['app']['id']}/offers")->assertCreated()->json('data');
    expect($offer['terms'])->toMatchArray(['amount' => ['amount' => '6000000.0000', 'currency' => 'NGN'], 'tenor_months' => 18, 'source' => 'counter_offer'])
        ->and($offer['schedule'])->toHaveCount(18);
})->group('FR-OFR-001');

it('re-issues an unsigned offer within the template limit, and routes a rejection back to credit (FR-OFR-007/008)', function () {
    LendingFixtures::activateConfig($this, 'offer.template', 'sme-offer', 'SME offer letter', LendingFixtures::offerTemplate(['max_reissues' => 1]));
    $case = offerReadyCase($this);
    $this->login($this->officer);
    $first = $this->api('POST', "/api/v1/applications/{$case['app']['id']}/offers")->assertCreated()->json('data');
    $second = $this->api('POST', "/api/v1/offers/{$first['id']}/actions/reissue", ['reason' => 'Corrected the repayment start date'])->assertCreated()->json('data');
    expect($second)->toMatchArray(['sequence' => 2, 'reissue_of' => $first['id'], 'reissue_count' => 1])
        ->and($this->api('GET', "/api/v1/offers/{$first['id']}")->json('data.status'))->toBe('superseded');
    $this->api('POST', "/api/v1/offers/{$second['id']}/actions/reissue", ['reason' => 'Another correction to the letter'])->assertStatus(422);

    $this->api('POST', "/api/v1/offers/{$second['id']}/actions/decline", ['outcome' => 'negotiate', 'reason_code' => 'CUST_RATE', 'note' => 'Customer asks for a lower rate given the guarantee.'])
        ->assertOk()->assertJsonPath('data.status', 'negotiating');
    expect(offerAppStatus($case['app']['id']))->toBe('assessment');
})->group('FR-OFR-007', 'FR-OFR-008');

it('expires an offer that is not accepted in time, and refuses signatures after expiry (FR-OFR-007)', function () {
    $case = offerReadyCase($this);
    $this->login($this->officer);
    $offer = $this->api('POST', "/api/v1/applications/{$case['app']['id']}/offers")->assertCreated()->json('data');
    $this->travel(31)->days();
    $this->login($this->officer);
    $this->api('POST', "/api/v1/offers/{$offer['id']}/signatories/{$offer['signatories'][0]['id']}/actions/sign", ['signed_at' => now()->toIso8601String()])
        ->assertStatus(422)->assertJsonPath('detail', fn ($d) => str_contains($d, 'expired'));
    Artisan::call('offers:expire');
    expect(offerAppStatus($case['app']['id']))->toBe('expired')
        ->and($this->api('GET', "/api/v1/offers/{$offer['id']}")->json('data.status'))->toBe('expired');
})->group('FR-OFR-007');

it('needs a standing approval: no offer before approval or after the approval lapsed (FR-APV-012)', function () {
    $case = offerReadyCase($this);
    $this->travel(61)->days();
    $this->login($this->officer);
    $this->api('POST', "/api/v1/applications/{$case['app']['id']}/offers")->assertStatus(422)->assertJsonPath('detail', fn ($d) => str_contains($d, 'lapsed'));
})->group('FR-APV-012');

<?php

declare(strict_types=1);

namespace Tests\Support;

use Fundly\Integration\Runtime\Models\AdapterBinding;
use Fundly\Modules\Access\Domain\Permission;
use Fundly\Modules\Application\Contracts\ApplicationLifecycle;
use Fundly\Modules\Application\Contracts\CanonicalStatus;
use Fundly\Shared\Security\Principal;
use Fundly\Shared\Security\SystemIdentity;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Whole-journey fixtures through the real API, for tests of later stages.
 * Users are created once per test and kept on the journey.
 */
final class Journey
{
    public UserCredentials $rm;

    public UserCredentials $analyst;

    public UserCredentials $approver;

    public UserCredentials $ops;

    /** @var array<string, array<string, mixed>> */
    public array $org;

    public function __construct(private readonly TestCase $t) {}

    /**
     * Tenant with simulators, org, product, policy, matrix, limits and offer template; the core cast of users.
     *
     * @param  list<Permission>  $approverExtra  extra permissions on the approver's role (e.g. to test checker exclusion)
     */
    public static function setUp(TestCase $t, array $approverExtra = []): self
    {
        $t->provisionTenant();
        LendingFixtures::bindSimulators();
        AdapterBinding::query()->where('port', 'core_banking')->update(['config' => ['account_names' => ['0123456789' => 'NWOSU AMAKA']]]);
        $j = new self($t);
        $j->org = LendingFixtures::orgTree($t);
        LendingFixtures::activateProduct($t, 'sme-term-loan', 'SME Term Loan', LendingFixtures::smeTermLoan());
        LendingFixtures::activateConfig($t, 'credit.rule_set', 'sme-policy', 'SME credit policy', LendingFixtures::smePolicy());
        LendingFixtures::activateConfig($t, 'approval.matrix', 'sme-matrix', 'SME approval matrix', LendingFixtures::smeMatrix());
        LendingFixtures::activateConfig($t, 'approval.authority_limits', 'default', 'Authority limits', LendingFixtures::authorityLimits());
        LendingFixtures::activateConfig($t, 'offer.template', 'sme-offer', 'SME offer letter', LendingFixtures::offerTemplate());
        $j->rm = $t->userWith([Permission::ApplicationView, Permission::ApplicationOriginate, Permission::PartyManage]);
        $j->analyst = $t->userWith([Permission::ApplicationView, Permission::CreditAnalyse, Permission::BureauPull, Permission::ApplicationRecommend]);
        $j->approver = $t->userWith(array_merge([Permission::ApplicationView, Permission::ApplicationApprove], $approverExtra), roleCode: 'credit_approver');
        $j->ops = $t->userWith([Permission::ApplicationView, Permission::OfferPrepare, Permission::ConditionManage]);

        return $j;
    }

    /**
     * An individual borrower (KYC-verified), approved without conditions,
     * offered, signed, accepted, and with a passing pre-disbursement check
     * against 058/0123456789: the application is ready for disbursement.
     *
     * @return array<string, mixed> the application
     */
    public function readyForDisbursement(string $amount = '3000000.00', int $tenor = 12): array
    {
        $t = $this->t;
        $t->login($this->rm);
        $borrower = LendingFixtures::person($t, 'Amaka', 'Nwosu', '22255566677', $this->org['lagos']['id']);
        LendingFixtures::consent($t, $borrower);
        $t->api('POST', "/api/v1/parties/{$borrower['id']}/identities/bvn/actions/verify")->assertOk();
        $app = $t->api('POST', '/api/v1/applications', [
            'legal_entity_id' => $this->org['le']['id'], 'org_unit_id' => $this->org['lagos']['id'], 'product_key' => 'sme-term-loan', 'primary_party_id' => $borrower['id'],
            'requested_amount' => $amount, 'tenor_months' => $tenor, 'purpose' => 'Inventory', 'data' => ['monthly_income' => '2500000.00', 'years_trading' => 5],
        ])->assertCreated()->json('data');
        $etag = fn (): string => (string) $t->api('GET', "/api/v1/applications/{$app['id']}")->headers->get('ETag');
        $t->api('POST', "/api/v1/applications/{$app['id']}/actions/submit", [], ['If-Match' => $etag()])->assertOk();
        $system = Principal::system($t->currentTenantFixture()->id, SystemIdentity::Workflow);
        foreach ([CanonicalStatus::Documentation, CanonicalStatus::Assessment] as $to) {
            app(ApplicationLifecycle::class)->advance($app['id'], $to, 'TEST_SETUP', null, $system);
        }
        $t->login($this->analyst);
        $t->api('POST', "/api/v1/applications/{$app['id']}/bureau-reports/actions/pull")->assertCreated();
        $t->api('POST', "/api/v1/applications/{$app['id']}/decisions/actions/run")->assertCreated();
        $t->api('POST', "/api/v1/applications/{$app['id']}/credit-memo", ['narrative' => 'Five years trading, affordable on stated income.', 'recommendation' => 'approve'])->assertCreated();
        $t->api('POST', "/api/v1/applications/{$app['id']}/actions/recommend", [], ['If-Match' => $etag()])->assertOk();
        $request = $t->api('GET', "/api/v1/applications/{$app['id']}/approval-requests")->json('data.0');
        $t->login($this->approver);
        $t->api('POST', "/api/v1/approval-requests/{$request['id']}/actions/vote", ['decision' => 'approve', 'rationale' => 'Affordable and within appetite.'])->assertOk();
        $t->login($this->ops);
        $offer = $t->api('POST', "/api/v1/applications/{$app['id']}/offers")->assertCreated()->json('data');
        foreach ($offer['signatories'] as $s) {
            $t->api('POST', "/api/v1/offers/{$offer['id']}/signatories/{$s['id']}/actions/sign", ['signed_at' => now()->toIso8601String()])->assertOk();
        }
        $t->api('POST', "/api/v1/offers/{$offer['id']}/actions/accept", ['file' => UploadedFile::fake()->createWithContent('executed.pdf', "%PDF-1.4\n%%EOF\n")])->assertOk();
        $t->api('POST', "/api/v1/applications/{$app['id']}/pre-disbursement-checks", ['bank_code' => '058', 'account_no' => '0123456789'])->assertCreated()->assertJsonPath('data.passed', true);

        return $app;
    }
}

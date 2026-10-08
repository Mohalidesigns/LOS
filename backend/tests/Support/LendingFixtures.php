<?php

declare(strict_types=1);

namespace Tests\Support;

use Fundly\Integration\Runtime\Models\AdapterBinding;
use Fundly\Integration\Runtime\Outbox\OutboxDispatcher;
use Fundly\Modules\Access\Domain\Permission;
use Tests\TestCase;

/**
 * P1 fixtures built through the real API: a product activated by
 * maker-checker, an org tree, and parties.
 */
final class LendingFixtures
{
    /** @return array<string, mixed> */
    public static function smeTermLoan(array $overrides = []): array
    {
        return array_replace_recursive([
            'category' => 'sme_term_loan',
            'segment' => 'sme',
            'currency' => 'NGN',
            'applicant_types' => ['limited_company', 'individual'],
            'amount' => ['min' => '500000.00', 'max' => '50000000.00'],
            'tenor_months' => ['min' => 3, 'max' => 36],
            'interest' => ['basis' => 'reducing_balance', 'rate_percent' => '24.5000'],
            'repayment_frequency' => 'monthly',
            'moratorium_months' => ['max' => 3],
            'fees' => [
                ['code' => 'MGMT', 'name' => 'Management fee', 'type' => 'upfront', 'calc' => 'percent', 'value' => '1.0000'],
                ['code' => 'CRI', 'name' => 'Credit life insurance', 'type' => 'upfront', 'calc' => 'percent', 'value' => '0.5000'],
            ],
            'penalty' => ['rate_percent' => '2.0000'],
            'prepayment' => ['allowed' => true, 'fee_percent' => '1.0000'],
            'eligibility' => [['code' => 'MIN_TRADING', 'description' => 'At least 12 months trading history']],
            'checklist' => [
                ['code' => 'CAC_CERT', 'name' => 'CAC certificate of incorporation', 'mandatory' => true, 'applies_to' => ['applicant_types' => ['limited_company']]],
                ['code' => 'STATEMENT_6M', 'name' => '6 months bank statements', 'mandatory' => true],
                ['code' => 'AUDITED_FS', 'name' => 'Audited financial statements', 'mandatory' => true, 'applies_to' => ['amount_min' => '10000000.00']],
                ['code' => 'GOVT_ID', 'name' => 'Government ID', 'mandatory' => true, 'applies_to' => ['applicant_types' => ['individual']]],
            ],
            'bindings' => ['workflow' => 'sme-standard', 'rule_set' => 'sme-policy', 'approval_matrix' => 'sme-matrix'],
            'offer_validity_days' => 30,
            'approval_validity_days' => 60,
        ], $overrides);
    }

    /**
     * Author → submit → approve → activation change request → checker approval.
     *
     * @param  array<string, mixed>  $content
     * @return array{artifact: array<string, mixed>, version: array<string, mixed>}
     */
    public static function activateProduct(TestCase $t, string $key, string $name, array $content): array
    {
        $author = $t->userWith([Permission::ConfigRead, Permission::ConfigAuthor, Permission::ConfigActivateRequest, Permission::ProductManage]);
        $checker = $t->userWith([Permission::ConfigRead, Permission::ConfigReview, Permission::ConfigActivateApprove, Permission::ChangeRequestRead]);
        $base = '/api/v1/config-artifacts/product';
        $t->login($author);
        $artifact = $t->api('GET', $base)->json('data');
        $existing = array_values(array_filter($artifact, static fn (array $a): bool => $a['key'] === $key))[0] ?? null;
        $artifact = $existing ?? $t->api('POST', $base, ['key' => $key, 'name' => $name])->assertCreated()->json('data');
        $v = $t->api('POST', "{$base}/{$artifact['id']}/versions", ['content' => $content])->assertCreated()->json('data');
        $t->api('POST', "{$base}/{$artifact['id']}/versions/{$v['id']}/actions/submit")->assertOk();
        $t->login($checker);
        $t->api('POST', "{$base}/{$artifact['id']}/versions/{$v['id']}/actions/approve")->assertOk();
        $t->login($author);
        $cr = $t->api('POST', "{$base}/{$artifact['id']}/versions/{$v['id']}/actions/activate")->assertStatus(202)->json('data');
        $t->login($checker);
        $t->api('POST', "/api/v1/change-requests/{$cr['id']}/actions/approve")->assertOk();

        return ['artifact' => $artifact, 'version' => $v];
    }

    /** @return array<string, array<string, mixed>> legal entity "DEMO" with branches LAGOS and KANO */
    public static function orgTree(TestCase $t): array
    {
        $t->login($t->currentTenantFixture()->admin());
        $le = $t->api('POST', '/api/v1/legal-entities', [
            'code' => 'DEMO', 'name' => 'Demo Bank Plc', 'jurisdiction' => 'NG', 'licence_category' => 'commercial_bank',
            'base_currency' => 'NGN', 'timezone' => 'Africa/Lagos', 'org_level_labels' => ['Branch'],
        ])->assertCreated()->json('data');
        $lagos = $t->api('POST', '/api/v1/org-units', ['legal_entity_id' => $le['id'], 'code' => 'LAGOS', 'name' => 'Lagos Island'])->assertCreated()->json('data');
        $kano = $t->api('POST', '/api/v1/org-units', ['legal_entity_id' => $le['id'], 'code' => 'KANO', 'name' => 'Kano Main'])->assertCreated()->json('data');

        return compact('le', 'lagos', 'kano');
    }

    /** @return array<string, mixed> */
    public static function company(TestCase $t, string $name = 'Adebayo Foods Limited', string $rc = 'RC1234567', ?string $orgUnitId = null): array
    {
        return $t->api('POST', '/api/v1/parties', [
            'type' => 'limited_company', 'company_name' => $name, 'registration_number' => $rc, 'incorporation_date' => '2015-03-01',
            'sector' => 'agro_processing', 'phone' => '08031234567', 'email' => 'finance@adebayofoods.ng', 'tin' => '12345678-0001',
            'address' => ['line1' => '14 Broad Street', 'city' => 'Lagos', 'state' => 'Lagos', 'country' => 'NG'], 'org_unit_id' => $orgUnitId,
        ])->assertCreated()->json('data');
    }

    /** @return array<string, mixed> */
    public static function person(TestCase $t, string $first, string $last, string $bvn, ?string $orgUnitId = null): array
    {
        return $t->api('POST', '/api/v1/parties', [
            'type' => 'individual', 'first_name' => $first, 'last_name' => $last, 'date_of_birth' => '1980-05-17', 'gender' => 'female',
            'nationality' => 'NG', 'phone' => '0802'.substr($bvn, -7), 'identities' => [['type' => 'bvn', 'value' => $bvn]], 'org_unit_id' => $orgUnitId,
        ])->assertCreated()->json('data');
    }

    /** Bind the identity and screening simulators for the current tenant (UAT posture, D-037). */
    public static function bindSimulators(array $screeningConfig = []): void
    {
        foreach ([['identity_verification', 'identity-simulator', []], ['screening', 'screening-simulator', $screeningConfig], ['malware_scan', 'malware-scan-simulator', []]] as [$port, $key, $config]) {
            AdapterBinding::query()->create(['port' => $port, 'adapter_key' => $key, 'adapter_version' => '1.0.0', 'config' => $config, 'processing_location' => 'on_prem:simulator', 'status' => 'active']);
        }
    }

    /** Run due outbox messages now (the scheduler does this every minute). */
    public static function drainOutbox(TestCase $t): array
    {
        return app(OutboxDispatcher::class)->dispatchDue($t->currentTenantFixture()->id);
    }

    /** @param list<string> $purposes */
    public static function consent(TestCase $t, array $party, array $purposes = ['data_processing', 'credit_bureau']): void
    {
        foreach ($purposes as $purpose) {
            $t->api('POST', "/api/v1/parties/{$party['id']}/consents", ['purpose' => $purpose, 'action' => 'grant', 'channel' => 'branch', 'terms_version' => 'T&C-2026.1', 'evidence_ref' => 'signed-form-001'])->assertCreated();
        }
    }
}

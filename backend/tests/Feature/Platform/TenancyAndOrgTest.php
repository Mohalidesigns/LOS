<?php

declare(strict_types=1);

use Fundly\Modules\Access\Domain\Permission;
use Fundly\Modules\Platform\Infrastructure\Models\LegalEntity;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->a = $this->provisionTenant('alpha');
    $this->b = $this->provisionTenant('beta');
});

it('isolates tenants at the application layer: B cannot see or reach A data, whatever the request says', function () {
    $this->useTenant($this->a);
    $this->login($this->a->admin());
    $leA = $this->api('POST', '/api/v1/legal-entities', ['code' => 'AAA', 'name' => 'Alpha Bank', 'jurisdiction' => 'NG', 'licence_category' => 'commercial_bank', 'base_currency' => 'NGN', 'timezone' => 'Africa/Lagos', 'org_level_labels' => ['Branch']])->json('data');

    $this->useTenant($this->b);
    $this->login($this->b->admin());
    expect($this->api('GET', '/api/v1/legal-entities')->json('data'))->toBe([]);
    $this->api('GET', "/api/v1/legal-entities/{$leA['id']}")->assertNotFound();
    // tenant_id in the body is ignored: the row lands in the principal's tenant
    $leB = $this->api('POST', '/api/v1/legal-entities', ['tenant_id' => $this->a->id, 'code' => 'BBB', 'name' => 'Beta', 'jurisdiction' => 'NG', 'licence_category' => 'commercial_bank', 'base_currency' => 'NGN', 'timezone' => 'Africa/Lagos', 'org_level_labels' => ['Branch']])->assertCreated()->json('data');
    expect(DB::table('legal_entities')->where('id', $leB['id'])->value('tenant_id'))->toBe($this->b->id);
    // B's admin cannot attach an org unit to A's legal entity
    $this->api('POST', '/api/v1/org-units', ['legal_entity_id' => $leA['id'], 'code' => 'X', 'name' => 'X'])->assertNotFound();
})->group('FR-TEN-001');

it('keeps each tenant audit chain separate', function () {
    $seqs = DB::table('audit_events')->select('tenant_id', DB::raw('min(seq) as lo'))->groupBy('tenant_id')->get();
    // RLS: from tenant B's context we only see B's chain
    expect($seqs)->toHaveCount(1)->and($seqs[0]->tenant_id)->toBe($this->b->id)->and((int) $seqs[0]->lo)->toBe(1);
})->group('FR-TEN-001', 'FR-AUD-004');

it('models legal entity → configurable levels with labels and enforces the configured depth', function () {
    $this->useTenant($this->a);
    $this->login($this->a->admin());
    $org = $this->createOrgTree();
    expect($org['le']['org_level_labels'])->toBe(['Region', 'Area', 'Branch'])
        ->and($org['north']['level_label'])->toBe('Region')->and($org['north']['depth'])->toBe(0)
        ->and($org['kano']['level_label'])->toBe('Area')->and($org['kano']['depth'])->toBe(1);
    $ikeja = $this->api('POST', '/api/v1/org-units', ['legal_entity_id' => $org['le']['id'], 'parent_id' => $org['kano']['id'], 'code' => 'K1', 'name' => 'Kano Main'])->assertCreated()->json('data');
    expect($ikeja['level_label'])->toBe('Branch');
    // a fourth level is beyond the configured labels
    $this->api('POST', '/api/v1/org-units', ['legal_entity_id' => $org['le']['id'], 'parent_id' => $ikeja['id'], 'code' => 'T1', 'name' => 'Team'])
        ->assertStatus(422)->assertJsonPath('code', 'domain-rule-violation');
    // add a level label (with If-Match) and it is allowed
    $le = $this->api('GET', "/api/v1/legal-entities/{$org['le']['id']}");
    $this->api('PATCH', "/api/v1/legal-entities/{$org['le']['id']}", ['org_level_labels' => ['Region', 'Area', 'Branch', 'Team']], ['If-Match' => $le->headers->get('ETag')])->assertOk();
    $this->api('POST', '/api/v1/org-units', ['legal_entity_id' => $org['le']['id'], 'parent_id' => $ikeja['id'], 'code' => 'T1', 'name' => 'Team'])->assertCreated()->assertJsonPath('data.level_label', 'Team');
    // cannot shrink labels below the existing depth
    $le = $this->api('GET', "/api/v1/legal-entities/{$org['le']['id']}");
    $this->api('PATCH', "/api/v1/legal-entities/{$org['le']['id']}", ['org_level_labels' => ['Region']], ['If-Match' => $le->headers->get('ETag')])->assertStatus(422);
})->group('FR-TEN-003');

it('maintains the closure table when a subtree moves, and scope follows the move', function () {
    $this->useTenant($this->a);
    $this->login($this->a->admin());
    $org = $this->createOrgTree();
    $branch = $this->api('POST', '/api/v1/org-units', ['legal_entity_id' => $org['le']['id'], 'parent_id' => $org['kano']['id'], 'code' => 'K1', 'name' => 'Kano Main'])->json('data');
    $southReader = $this->userWith([Permission::OrgUnitRead], ['org_unit_id' => $org['south']['id']]);

    $kano = $this->api('GET', "/api/v1/org-units/{$org['kano']['id']}");
    // cannot move a node under its own descendant
    $this->api('PATCH', "/api/v1/org-units/{$org['kano']['id']}", ['parent_id' => $branch['id']], ['If-Match' => $kano->headers->get('ETag')])->assertStatus(422);
    $this->api('PATCH', "/api/v1/org-units/{$org['kano']['id']}", ['parent_id' => $org['south']['id']], ['If-Match' => $kano->headers->get('ETag')])
        ->assertOk()->assertJsonPath('data.parent_id', $org['south']['id'])->assertJsonPath('data.depth', 1);

    $ancestors = DB::table('org_unit_closure')->where('descendant_id', $branch['id'])->orderByDesc('depth')->pluck('ancestor_id')->all();
    expect($ancestors)->toBe([$org['south']['id'], $org['kano']['id'], $branch['id']]);
    expect(DB::table('org_units')->where('id', $branch['id'])->value('depth'))->toBe(2);

    $this->login($southReader);
    expect(array_column($this->api('GET', '/api/v1/org-units')->json('data'), 'code'))->toEqualCanonicalizing(['SOUTH', 'KANO', 'K1']);
})->group('FR-TEN-003', 'FR-SEC-005');

it('stores jurisdiction, licence category, base currency and timezone on the legal entity', function () {
    $this->useTenant($this->a);
    $this->login($this->a->admin());
    $this->api('POST', '/api/v1/legal-entities', ['code' => 'BAD', 'name' => 'x', 'jurisdiction' => 'Nigeria', 'licence_category' => 'pmb', 'base_currency' => 'naira', 'timezone' => 'Mars/Base', 'org_level_labels' => []])
        ->assertStatus(422)->assertJsonStructure(['errors' => ['jurisdiction', 'licence_category', 'base_currency', 'timezone', 'org_level_labels']]);
    $le = $this->api('POST', '/api/v1/legal-entities', ['code' => 'MB1', 'name' => 'Merchant', 'jurisdiction' => 'NG', 'licence_category' => 'merchant_bank', 'base_currency' => 'NGN', 'timezone' => 'Africa/Lagos', 'org_level_labels' => ['Branch']])->assertCreated()->json('data');
    $m = LegalEntity::query()->findOrFail($le['id']);
    expect([$m->jurisdiction, $m->licence_category, $m->base_currency, $m->timezone])->toBe(['NG', 'merchant_bank', 'NGN', 'Africa/Lagos']);
})->group('FR-TEN-003');

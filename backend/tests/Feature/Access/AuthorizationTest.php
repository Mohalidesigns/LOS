<?php

declare(strict_types=1);

use Fundly\Modules\Access\Domain\Permission;
use Fundly\Modules\Access\Domain\RoleLibrary;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->tenant = $this->provisionTenant();
    $this->login($this->tenant->admin());
    $this->org = $this->createOrgTree();
});

it('ships the 19-role standard library as tenant-modifiable, non-assignable templates', function () {
    $r = $this->api('GET', '/api/v1/roles', [], [])->assertOk();
    $templates = $this->api('GET', '/api/v1/roles?filter[is_template]=true&page[size]=50')->assertOk()->json('data');
    expect($templates)->toHaveCount(19)
        ->and(array_column($templates, 'template_key'))->toEqualCanonicalizing(array_keys(RoleLibrary::templates()));
    $auditor = collect($templates)->firstWhere('template_key', 'auditor');
    foreach ($auditor['permissions'] as $p) {
        expect(Permission::from($p)->isReadOnly() || $p === 'audit:verify')->toBeTrue("auditor template must be read-only, found {$p}");
    }
})->group('LOS-FR-278', 'FR-SEC-002');

it('creates new roles with no permissions and denies by default', function () {
    $role = $this->api('POST', '/api/v1/roles', ['code' => 'branch_clerk', 'name' => 'Branch clerk'])->assertCreated();
    expect($role->json('data.permissions'))->toBe([]);

    $nobody = $this->userWith([]);
    $this->login($nobody);
    $this->api('GET', '/api/v1/users')->assertForbidden()->assertJsonPath('code', 'forbidden');
    $this->api('GET', '/api/v1/legal-entities')->assertForbidden();
})->group('FR-SEC-003');

it('clones a template into an assignable role and lets the tenant edit it via maker-checker', function () {
    $tpl = collect($this->api('GET', '/api/v1/roles?filter[is_template]=true&page[size]=50')->json('data'))->firstWhere('template_key', 'credit_analyst');
    $clone = $this->api('POST', "/api/v1/roles/{$tpl['id']}/actions/clone", ['code' => 'ca_lagos', 'name' => 'Credit Analyst (Lagos)'])->assertCreated();
    expect($clone->json('data.permissions'))->toEqual($tpl['permissions'])->and($clone->json('data.is_template'))->toBeFalse();

    $etag = $clone->headers->get('ETag');
    $this->api('PATCH', "/api/v1/roles/{$clone->json('data.id')}", ['name' => 'Renamed'])->assertStatus(428);
    $this->api('PATCH', "/api/v1/roles/{$clone->json('data.id')}", ['name' => 'Renamed'], ['If-Match' => '"stale"'])->assertStatus(412);
    $this->api('PATCH', "/api/v1/roles/{$clone->json('data.id')}", ['name' => 'Renamed'], ['If-Match' => $etag])->assertOk()->assertJsonPath('data.name', 'Renamed');

    $cr = $this->api('PUT', "/api/v1/roles/{$clone->json('data.id')}/permissions", ['permissions' => ['application:view', 'credit:analyse', 'report:view']])->assertStatus(202);
    expect($cr->json('data.status'))->toBe('pending');
    // not applied until a checker approves
    expect($this->api('GET', "/api/v1/roles/{$clone->json('data.id')}")->json('data.permissions'))->toEqual($tpl['permissions']);

    $this->login($this->tenant->admin(1));
    $this->api('POST', "/api/v1/change-requests/{$cr->json('data.id')}/actions/approve")->assertOk()->assertJsonPath('data.status', 'executed');
    expect($this->api('GET', "/api/v1/roles/{$clone->json('data.id')}")->json('data.permissions'))->toEqual(['application:view', 'credit:analyse', 'report:view']);
})->group('FR-SEC-002', 'FR-SEC-001', 'FR-SEC-007');

it('grants the union of all assignments', function () {
    $tenant = $this->tenantContext()->requireId();
    $u = $this->userWith([Permission::RoleRead]);
    // a second assignment with a different role
    $role2 = \Fundly\Modules\Access\Infrastructure\Models\Role::query()->create(['code' => 'r2', 'name' => 'r2', 'is_template' => false]);
    \Fundly\Modules\Access\Infrastructure\Models\RolePermission::query()->create(['role_id' => $role2->id, 'permission_code' => 'legal_entity:read']);
    \Fundly\Modules\Access\Infrastructure\Models\RoleAssignment::query()->create(['user_id' => $u->id, 'role_id' => $role2->id, 'scope' => \Fundly\Modules\Access\Domain\Scope::unrestricted()->toArray(), 'valid_from' => now()->subMinute(), 'granted_by' => 'test']);
    $this->login($u);
    $this->api('GET', '/api/v1/roles')->assertOk();
    $this->api('GET', '/api/v1/legal-entities')->assertOk();
    $this->api('GET', '/api/v1/users')->assertForbidden();
})->group('FR-SEC-004');

it('filters lists by scope and refuses out-of-scope single reads', function () {
    $north = $this->org['north'];
    $u = $this->userWith([Permission::OrgUnitRead], ['org_unit_id' => $north['id']]);
    $this->login($u);

    $codes = array_column($this->api('GET', '/api/v1/org-units')->assertOk()->json('data'), 'code');
    expect($codes)->toEqualCanonicalizing(['NORTH', 'KANO']);
    $this->api('GET', "/api/v1/org-units/{$this->org['kano']['id']}")->assertOk();
    $this->api('GET', "/api/v1/org-units/{$this->org['south']['id']}")->assertForbidden();
    // the denial is audited with the reason
    expect(DB::table('audit_events')->where('action', 'authz.denied')->where('reason_code', 'out_of_scope')->exists())->toBeTrue();
})->group('FR-SEC-005', 'FR-AUD-007');

it('combines legal-entity and org scope in one assignment', function () {
    $other = $this->api('POST', '/api/v1/legal-entities', [
        'code' => 'XYZ', 'name' => 'XYZ MFB', 'jurisdiction' => 'NG', 'licence_category' => 'commercial_bank',
        'base_currency' => 'NGN', 'timezone' => 'Africa/Lagos', 'org_level_labels' => ['Region'],
    ])->json('data');
    $u = $this->userWith([Permission::LegalEntityRead], ['legal_entity_ids' => [$this->org['le']['id']]]);
    $this->login($u);
    $ids = array_column($this->api('GET', '/api/v1/legal-entities')->json('data'), 'id');
    expect($ids)->toBe([$this->org['le']['id']]);
    $this->api('GET', "/api/v1/legal-entities/{$other['id']}")->assertForbidden();
})->group('FR-SEC-005');

it('shows effective access with the granting assignment, for self and (with user:read) others', function () {
    $u = $this->userWith([Permission::OrgUnitRead, Permission::AuditRead], ['org_unit_id' => $this->org['north']['id']]);
    $this->login($u);
    $ea = $this->api('GET', '/api/v1/me/effective-access')->assertOk()->json('data');
    expect(array_column($ea['permissions'], 'permission'))->toBe(['audit:read', 'org_unit:read'])
        ->and($ea['permissions'][1]['granted_by'][0]['scope']['org_unit_id'])->toBe($this->org['north']['id'])
        ->and($ea['permissions'][1]['granted_by'][0]['assignment_id'])->toBe($ea['assignments'][0]['assignment_id']);

    $this->login($this->tenant->admin());
    $this->api('GET', "/api/v1/users/{$u->id}/effective-access")->assertOk()->assertJsonPath('data.user_id', $u->id);
})->group('FR-SEC-011');

it('masks sensitive fields for readers without the field permission', function () {
    $reader = $this->userWith([Permission::UserRead]);
    $target = $this->userWith([]);
    $this->login($reader);
    $masked = $this->api('GET', "/api/v1/users/{$target->id}")->assertOk()->json('data.email');
    expect($masked)->toContain('*')->not->toBe($target->email);
    $this->login($this->tenant->admin());
    expect($this->api('GET', "/api/v1/users/{$target->id}")->json('data.email'))->toBe($target->email);
})->group('FR-SEC-001', 'FR-CMP-036');

it('lists the code-defined permission catalogue', function () {
    $perms = $this->api('GET', '/api/v1/permissions')->assertOk()->json('data');
    expect(array_column($perms, 'code'))->toEqualCanonicalizing(Permission::codes());
    expect(DB::table('permissions')->count())->toBe(count(Permission::cases()));
})->group('FR-SEC-001');

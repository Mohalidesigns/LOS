<?php

declare(strict_types=1);

use Fundly\Modules\Access\Domain\Permission;
use Fundly\Modules\Access\Domain\Scope;
use Fundly\Modules\Access\Infrastructure\Models\Role;
use Fundly\Modules\Access\Infrastructure\Models\RoleAssignment;
use Fundly\Modules\Access\Infrastructure\Models\RolePermission;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->tenant = $this->provisionTenant();
    [$this->maker, $this->checker] = [$this->tenant->admin(0), $this->tenant->admin(1)];
    $this->makerRole = Role::query()->create(['code' => 'disb_maker', 'name' => 'Disbursement maker', 'is_template' => false]);
    RolePermission::query()->create(['role_id' => $this->makerRole->id, 'permission_code' => 'disbursement:make']);
    $this->checkerRole = Role::query()->create(['code' => 'disb_checker', 'name' => 'Disbursement checker', 'is_template' => false]);
    RolePermission::query()->create(['role_id' => $this->checkerRole->id, 'permission_code' => 'disbursement:check']);
});

it('seeds default SoD rules and lets administrators manage them', function () {
    $this->login($this->maker);
    $rules = $this->api('GET', '/api/v1/sod-rules')->assertOk()->json('data');
    expect(collect($rules)->pluck('left')->all())->toContain('disbursement:make', 'application:originate');

    $r = $this->api('POST', '/api/v1/sod-rules', ['kind' => 'permission_pair', 'left' => 'config:author', 'right' => 'config:review', 'description' => 'Authors may not review'])->assertCreated();
    $this->api('POST', '/api/v1/sod-rules', ['kind' => 'permission_pair', 'left' => 'config:review', 'right' => 'config:author', 'description' => 'dup'])->assertStatus(409);
    $this->api('POST', '/api/v1/sod-rules', ['kind' => 'permission_pair', 'left' => 'nope:x', 'right' => 'config:author', 'description' => 'x'])->assertStatus(422);
    $this->api('DELETE', "/api/v1/sod-rules/{$r->json('data.id')}")->assertNoContent();
})->group('FR-SEC-006');

it('blocks a conflicting assignment at request time', function () {
    $user = $this->userWith([Permission::DisbursementMake]);
    $this->login($this->maker);
    $r = $this->api('POST', '/api/v1/role-assignments', ['user_id' => $user->id, 'role_id' => $this->checkerRole->id, 'scope' => []])
        ->assertStatus(409)->assertJsonPath('code', 'sod-conflict');
    expect($r->json('conflicts.0.left'))->toBe('disbursement:make');
})->group('FR-SEC-006');

it('blocks a conflicting assignment at execution time if the conflict appeared after the request', function () {
    $user = $this->userWith([]);
    $this->login($this->maker);
    $cr = $this->api('POST', '/api/v1/role-assignments', ['user_id' => $user->id, 'role_id' => $this->checkerRole->id, 'scope' => []])->assertStatus(202)->json('data');
    // the user meanwhile acquires the maker side
    RoleAssignment::query()->create(['user_id' => $user->id, 'role_id' => $this->makerRole->id, 'scope' => Scope::unrestricted()->toArray(), 'valid_from' => now(), 'granted_by' => 'test']);
    $this->login($this->checker);
    $this->api('POST', "/api/v1/change-requests/{$cr['id']}/actions/approve")->assertStatus(409);
    expect(DB::table('role_assignments')->where('user_id', $user->id)->where('role_id', $this->checkerRole->id)->exists())->toBeFalse();
})->group('FR-SEC-006', 'FR-SEC-007');

it('blocks a role bundle that combines both sides of a rule', function () {
    $this->login($this->maker);
    $this->api('PUT', "/api/v1/roles/{$this->makerRole->id}/permissions", ['permissions' => ['disbursement:make', 'disbursement:check']])
        ->assertStatus(409)->assertJsonPath('code', 'sod-conflict');
})->group('FR-SEC-006');

it('blocks at action time a user who holds a conflicting pair (pre-existing violation), and reports it', function () {
    $both = $this->userWith([Permission::DisbursementMake, Permission::DisbursementCheck, Permission::RoleRead]);
    $this->login($this->maker);
    $conflicts = $this->api('GET', '/api/v1/sod-conflicts')->assertOk()->json('data');
    expect(collect($conflicts)->where('user_id', $both->id)->pluck('left')->all())->toContain('disbursement:make');

    // action-time check through the authoriser
    $principal = new Fundly\Shared\Security\Principal($both->id, $this->tenant->id, Fundly\Shared\Security\PrincipalKind::Human);
    $gate = app(Fundly\Shared\Security\AuthorizationGate::class);
    expect(fn () => $gate->authorize($principal, 'disbursement:check'))->toThrow(Fundly\Shared\Security\AccessDenied::class)
        ->and($gate->authorize($principal, 'role:read')->allowed)->toBeTrue();
})->group('FR-SEC-006');

it('blocks a user from exercising the counterpart permission on the same record (history-based SoD)', function () {
    $maker = $this->userWith([Permission::DisbursementMake]);
    $gate = app(Fundly\Shared\Security\AuthorizationGate::class);
    $d1 = new Fundly\Shared\Security\ResourceAttributes(entityType: 'disbursement', entityId: 'D-1');
    $d2 = new Fundly\Shared\Security\ResourceAttributes(entityType: 'disbursement', entityId: 'D-2');

    // The user "made" D-1; the audit trail is the record of who did what.
    app(Fundly\Shared\Security\CurrentPrincipal::class)->set(new Fundly\Shared\Security\Principal($maker->id, $this->tenant->id, Fundly\Shared\Security\PrincipalKind::Human));
    app(Fundly\Shared\Audit\AuditTrail::class)->record(new Fundly\Shared\Audit\AuditEntry('disbursement.made', entityType: 'disbursement', entityId: 'D-1', permission: 'disbursement:make'));

    // Later they move teams: maker access revoked, checker access granted. No standing conflict now.
    RoleAssignment::query()->where('user_id', $maker->id)->update(['revoked_at' => now()]);
    RoleAssignment::query()->create(['user_id' => $maker->id, 'role_id' => $this->checkerRole->id, 'scope' => Scope::unrestricted()->toArray(), 'valid_from' => now()->subMinute(), 'granted_by' => 'test']);
    app(Fundly\Modules\Access\Infrastructure\Persistence\GrantRepository::class)->forget();

    $p = new Fundly\Shared\Security\Principal($maker->id, $this->tenant->id, Fundly\Shared\Security\PrincipalKind::Human);
    expect($gate->authorize($p, 'disbursement:check', $d2)->allowed)->toBeTrue()
        ->and(fn () => $gate->authorize($p, 'disbursement:check', $d1))->toThrow(Fundly\Shared\Security\AccessDenied::class, 'already performed a conflicting action');
})->group('FR-SEC-006');

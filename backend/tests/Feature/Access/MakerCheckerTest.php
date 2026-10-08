<?php

declare(strict_types=1);

use Fundly\Modules\Access\Domain\Permission;
use Fundly\Modules\Access\Infrastructure\Models\ChangeRequest;
use Fundly\Modules\Access\Infrastructure\Models\Role;
use Fundly\Modules\Access\Infrastructure\Models\RolePermission;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->tenant = $this->provisionTenant();
    [$this->maker, $this->checker] = [$this->tenant->admin(0), $this->tenant->admin(1)];
    $this->role = Role::query()->create(['code' => 'auditor_lagos', 'name' => 'Auditor', 'is_template' => false]);
    RolePermission::query()->create(['role_id' => $this->role->id, 'permission_code' => 'audit:read']);
    $this->target = $this->userWith([]);
});

function requestAssignment(object $t, array $extra = []): Illuminate\Testing\TestResponse
{
    return $t->api('POST', '/api/v1/role-assignments', array_merge([
        'user_id' => $t->target->id, 'role_id' => $t->role->id, 'scope' => [], 'reason' => 'joining audit team',
    ], $extra));
}

it('only creates a role assignment once a different user approves it', function () {
    $this->login($this->maker);
    $cr = requestAssignment($this)->assertStatus(202)->json('data');
    expect($cr['status'])->toBe('pending')->and($cr['action_type'])->toBe('access.role_assignment.grant')
        ->and($cr['required_checker_permission'])->toBe('role_assignment:approve');
    expect(DB::table('role_assignments')->where('user_id', $this->target->id)->where('role_id', $this->role->id)->exists())->toBeFalse();

    // the maker cannot approve their own request
    $this->api('POST', "/api/v1/change-requests/{$cr['id']}/actions/approve")->assertForbidden()->assertJsonPath('code', 'sod-conflict');

    $this->login($this->checker);
    $done = $this->api('POST', "/api/v1/change-requests/{$cr['id']}/actions/approve", ['reason' => 'verified with HR'])->assertOk()->json('data');
    expect($done['status'])->toBe('executed')->and($done['checker_id'])->toBe($this->checker->id)
        ->and($done['execution_result']['role_assignment_id'])->toBeString();
    $assignment = DB::table('role_assignments')->where('id', $done['execution_result']['role_assignment_id'])->first();
    expect($assignment->change_request_id)->toBe($cr['id'])->and($assignment->granted_by)->toBe($this->checker->id);

    // the target user now holds audit:read
    $this->login($this->target);
    $this->api('GET', '/api/v1/audit-events')->assertOk();
})->group('FR-SEC-007', 'FR-SEC-004');

it('does not let the subject of a change approve it, nor a checker without the checker permission', function () {
    $this->login($this->maker);
    $cr = requestAssignment($this)->json('data');

    $weak = $this->userWith([Permission::ChangeRequestRead]);
    $this->login($weak);
    $this->api('POST', "/api/v1/change-requests/{$cr['id']}/actions/approve")->assertForbidden()->assertJsonPath('code', 'forbidden');

    // the target is excluded even if they could otherwise approve
    $role = Role::query()->where('code', 'tenant_administrator')->firstOrFail();
    \Fundly\Modules\Access\Infrastructure\Models\RoleAssignment::query()->create(['user_id' => $this->target->id, 'role_id' => $role->id, 'scope' => \Fundly\Modules\Access\Domain\Scope::unrestricted()->toArray(), 'valid_from' => now()->subMinute(), 'granted_by' => 'test']);
    $this->login($this->target);
    $this->api('POST', "/api/v1/change-requests/{$cr['id']}/actions/approve")->assertForbidden()->assertJsonPath('code', 'sod-conflict');
})->group('FR-SEC-007', 'FR-SEC-006');

it('enforces checker ≠ maker in the database as well', function () {
    $this->login($this->maker);
    $cr = requestAssignment($this)->json('data');
    expect(fn () => DB::transaction(fn () => DB::table('change_requests')->where('id', $cr['id'])->update(['checker_id' => $this->maker->id])))
        ->toThrow(QueryException::class, 'change_requests_four_eyes_chk');
})->group('FR-SEC-007');

it('fails stale when the state changed between request and approval', function () {
    $this->login($this->maker);
    $cr = requestAssignment($this)->json('data');
    // meanwhile the role's bundle changes (as if another change request executed)
    RolePermission::query()->create(['role_id' => $this->role->id, 'permission_code' => 'user:read']);

    $this->login($this->checker);
    $r = $this->api('POST', "/api/v1/change-requests/{$cr['id']}/actions/approve")->assertStatus(409)->assertJsonPath('code', 'change-request-stale');
    expect($r->json('change_request.status'))->toBe('failed_stale');
    expect(ChangeRequest::query()->findOrFail($cr['id'])->status)->toBe('failed_stale')
        ->and(DB::table('role_assignments')->where('user_id', $this->target->id)->where('role_id', $this->role->id)->exists())->toBeFalse();
    $this->api('POST', "/api/v1/change-requests/{$cr['id']}/actions/approve")->assertStatus(409)->assertJsonPath('code', 'change-request-not-pending');
})->group('FR-SEC-007');

it('re-validates on execution (a request that became invalid is not applied)', function () {
    $this->login($this->maker);
    $cr = requestAssignment($this)->json('data');
    DB::table('users')->where('id', $this->target->id)->update(['status' => 'disabled']);
    $this->login($this->checker);
    $this->api('POST', "/api/v1/change-requests/{$cr['id']}/actions/approve")->assertStatus(409)->assertJsonPath('code', 'change-request-invalid');
})->group('FR-SEC-007');

it('supports reject (reason mandatory) and cancel by the maker only', function () {
    $this->login($this->maker);
    $a = requestAssignment($this)->json('data');
    $b = requestAssignment($this, ['reason' => 'second'])->json('data');

    $this->login($this->checker);
    $this->api('POST', "/api/v1/change-requests/{$a['id']}/actions/reject")->assertStatus(422);
    $this->api('POST', "/api/v1/change-requests/{$a['id']}/actions/reject", ['reason' => 'not justified'])->assertOk()->assertJsonPath('data.status', 'rejected');
    $this->api('POST', "/api/v1/change-requests/{$b['id']}/actions/cancel")->assertForbidden();

    $this->login($this->maker);
    $this->api('POST', "/api/v1/change-requests/{$b['id']}/actions/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');
    expect(array_column($this->api('GET', '/api/v1/change-requests?filter[status]=rejected')->json('data'), 'id'))->toBe([$a['id']]);
})->group('FR-SEC-007');

it('requires step-up for approval', function () {
    $this->login($this->maker);
    $cr = requestAssignment($this)->json('data');
    $this->login($this->checker);
    $this->travel(6)->minutes();
    $this->api('POST', "/api/v1/change-requests/{$cr['id']}/actions/approve")->assertStatus(401)->assertJsonPath('code', 'step-up-required');
})->group('FR-SEC-013', 'FR-SEC-007');

it('revokes an assignment through maker-checker and forces the user to re-authenticate', function () {
    $this->login($this->maker);
    $cr = requestAssignment($this)->json('data');
    $this->login($this->checker);
    $assignmentId = $this->api('POST', "/api/v1/change-requests/{$cr['id']}/actions/approve")->json('data.execution_result.role_assignment_id');

    $this->login($this->target);
    $this->api('GET', '/api/v1/audit-events')->assertOk();
    $targetJar = $this->jar;

    $this->login($this->maker);
    $rev = $this->api('DELETE', "/api/v1/role-assignments/{$assignmentId}", ['reason' => 'left team'])->assertStatus(202)->json('data');
    $this->login($this->checker);
    $this->api('POST', "/api/v1/change-requests/{$rev['id']}/actions/approve")->assertOk();

    $this->jar = $targetJar;
    $this->api('GET', '/api/v1/audit-events')->assertStatus(401);
    $this->login($this->target);
    $this->api('GET', '/api/v1/audit-events')->assertForbidden();
})->group('FR-SEC-007', 'FR-SEC-015');

it('honours effective dating on approved assignments (temporary elevation expires)', function () {
    $this->login($this->maker);
    $cr = requestAssignment($this, ['valid_from' => now()->toIso8601String(), 'valid_to' => now()->addHours(2)->toIso8601String()])->json('data');
    $this->login($this->checker);
    $this->api('POST', "/api/v1/change-requests/{$cr['id']}/actions/approve")->assertOk();

    $this->login($this->target);
    $this->api('GET', '/api/v1/audit-events')->assertOk();
    $this->travel(3)->hours();
    $this->login($this->target);
    $this->api('GET', '/api/v1/audit-events')->assertForbidden();
})->group('FR-SEC-008');

it('audits the full maker-checker lifecycle', function () {
    $this->login($this->maker);
    $cr = requestAssignment($this)->json('data');
    $this->login($this->checker);
    $this->api('POST', "/api/v1/change-requests/{$cr['id']}/actions/approve");
    $events = DB::table('audit_events')->whereIn('action', ['change_request.submitted', 'change_request.executed', 'access.role_assignment.granted'])->orderBy('seq')->get();
    expect($events->pluck('action')->all())->toBe(['change_request.submitted', 'access.role_assignment.granted', 'change_request.executed'])
        ->and($events[0]->actor_id)->toBe($this->maker->id)
        ->and($events[2]->actor_id)->toBe($this->checker->id)
        ->and($events[2]->step_up_ref)->not->toBeNull();
})->group('FR-AUD-007', 'FR-AUD-001');

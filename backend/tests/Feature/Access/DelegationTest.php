<?php

declare(strict_types=1);

use Fundly\Modules\Access\Domain\Permission;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->tenant = $this->provisionTenant();
    $this->approver = $this->userWith([Permission::AuditRead, Permission::DelegationCreate]);
    $this->deputy = $this->userWith([]);
    $this->assignmentId = DB::table('role_assignments')->where('user_id', $this->approver->id)->value('id');
});

it('delegates an assignment for a bounded period with mandatory expiry', function () {
    $this->login($this->approver);
    $this->api('POST', '/api/v1/delegations', ['role_assignment_id' => $this->assignmentId, 'delegate_id' => $this->deputy->id, 'reason' => 'leave'])->assertStatus(422);
    $this->api('POST', '/api/v1/delegations', ['role_assignment_id' => $this->assignmentId, 'delegate_id' => $this->deputy->id, 'valid_to' => now()->addDays(45)->toIso8601String(), 'reason' => 'leave'])
        ->assertStatus(422)->assertJsonPath('errors.valid_to.0', 'A delegation may last at most 30 days.');
    $d = $this->api('POST', '/api/v1/delegations', ['role_assignment_id' => $this->assignmentId, 'delegate_id' => $this->deputy->id, 'valid_from' => now()->toIso8601String(), 'valid_to' => now()->addDays(2)->toIso8601String(), 'reason' => 'annual leave'])
        ->assertCreated()->json('data');

    $this->login($this->deputy);
    $this->api('GET', '/api/v1/audit-events')->assertOk();
    $ea = $this->api('GET', '/api/v1/me/effective-access')->json('data');
    expect(collect($ea['permissions'])->firstWhere('permission', 'audit:read')['granted_by'][0]['delegated_by'])->toBe($this->approver->id);

    $this->travel(3)->days();
    $this->login($this->deputy);
    $this->api('GET', '/api/v1/audit-events')->assertForbidden();
    expect($d['valid_to'])->not->toBeNull();
})->group('FR-SEC-008');

it('only lets you delegate your own assignments, and lets the delegator revoke', function () {
    $other = $this->userWith([Permission::DelegationCreate]);
    $this->login($other);
    $this->api('POST', '/api/v1/delegations', ['role_assignment_id' => $this->assignmentId, 'delegate_id' => $this->deputy->id, 'valid_to' => now()->addDay()->toIso8601String(), 'reason' => 'x'])->assertStatus(422);

    $this->login($this->approver);
    $d = $this->api('POST', '/api/v1/delegations', ['role_assignment_id' => $this->assignmentId, 'delegate_id' => $this->deputy->id, 'valid_to' => now()->addDay()->toIso8601String(), 'reason' => 'x'])->json('data');
    expect(array_column($this->api('GET', '/api/v1/delegations')->json('data'), 'id'))->toBe([$d['id']]);
    $this->api('DELETE', "/api/v1/delegations/{$d['id']}")->assertOk()->assertJsonPath('data.id', $d['id']);
    $this->login($this->deputy);
    $this->api('GET', '/api/v1/audit-events')->assertForbidden();
})->group('FR-SEC-008');

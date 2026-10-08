<?php

declare(strict_types=1);

use Fundly\Modules\Access\Infrastructure\Models\User;
use Fundly\Shared\Id\UuidV7;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
 * These tests COMMIT data with two tenants and then attack isolation from the
 * runtime connection with raw SQL, bypassing every application-level filter.
 */
beforeEach(function () {
    $this->a = $this->provisionTenant('rls-a');
    $this->b = $this->provisionTenant('rls-b');
    $this->tenantContext()->clear();
});

it('connects as a non-owner, non-superuser role that cannot bypass RLS', function () {
    $role = DB::selectOne('select current_user as u, r.rolsuper, r.rolbypassrls from pg_roles r where r.rolname = current_user');
    expect($role->u)->toBe('fundly_app')->and($role->rolsuper)->toBeFalse()->and($role->rolbypassrls)->toBeFalse();
    $owner = DB::selectOne('select tableowner from pg_tables where tablename = ?', ['users']);
    expect($owner->tableowner)->toBe('fundly_owner');
    $flags = DB::select("select relname, relrowsecurity, relforcerowsecurity from pg_class where relname in ('users','legal_entities','org_units','role_assignments','audit_events','outbox_messages','integration_calls','change_requests','config_versions')");
    foreach ($flags as $f) {
        expect($f->relrowsecurity)->toBeTrue("{$f->relname} RLS")->and($f->relforcerowsecurity)->toBeTrue("{$f->relname} FORCE RLS");
    }
})->group('FR-TEN-001');

it('lets tenant A read only its own rows, even via raw SQL on the app connection', function () {
    $this->useTenant($this->a);
    $emails = array_column(DB::select('select email from users'), 'email');
    expect($emails)->toEqualCanonicalizing(['admin1@rls-a.test', 'admin2@rls-a.test']);
    expect(DB::selectOne('select count(*) as n from users where tenant_id = ?', [$this->b->id])->n)->toBe(0)
        ->and(DB::selectOne('select count(*) as n from audit_events where tenant_id = ?', [$this->b->id])->n)->toBe(0)
        ->and(DB::selectOne('select count(*) as n from roles')->n)->toBe(DB::selectOne('select count(*) as n from roles where tenant_id = ?', [$this->a->id])->n);
    // Eloquent without the global scope still sees only tenant A
    expect(User::withoutGlobalScopes()->count())->toBe(2);
})->group('FR-TEN-001');

it('returns nothing without a tenant context', function () {
    expect(DB::selectOne('select count(*) as n from users')->n)->toBe(0)
        ->and(DB::selectOne('select count(*) as n from audit_events')->n)->toBe(0);
})->group('FR-TEN-001');

it('refuses writes into another tenant (WITH CHECK) and updates/deletes of its rows', function () {
    $this->useTenant($this->a);
    expect(fn () => DB::table('sod_rules')->insert(['id' => UuidV7::generate(), 'tenant_id' => $this->b->id, 'kind' => 'permission_pair', 'left_ref' => 'a:a', 'right_ref' => 'b:b', 'description' => 'x', 'created_at' => now(), 'updated_at' => now()]))
        ->toThrow(QueryException::class, 'row-level security');
    expect(DB::update('update users set name = ? where tenant_id = ?', ['pwned', $this->b->id]))->toBe(0)
        ->and(DB::delete('delete from role_assignments where tenant_id = ?', [$this->b->id]))->toBe(0);
    $this->useTenant($this->b);
    expect(DB::table('users')->where('name', 'pwned')->exists())->toBeFalse();
})->group('FR-TEN-001');

it('cannot switch off RLS or escalate from the runtime role', function () {
    expect(fn () => DB::statement('alter table users disable row level security'))->toThrow(QueryException::class, 'must be owner')
        ->and(fn () => DB::statement('set role fundly_owner'))->toThrow(QueryException::class, 'permission denied');
})->group('FR-TEN-001');

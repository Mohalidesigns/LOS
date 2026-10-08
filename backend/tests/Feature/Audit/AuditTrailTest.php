<?php

declare(strict_types=1);

use Fundly\Modules\Access\Domain\Permission;
use Fundly\Shared\Audit\Actor;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Audit\AuditTrail;
use Fundly\Shared\Audit\AuditVerifier;
use Fundly\Shared\Security\SystemIdentity;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->tenant = $this->provisionTenant();
    $this->login($this->tenant->admin());
});

it('captures actor, roles snapshot, permissions hash, IP, user agent, correlation id, before/after and reason', function () {
    $u = $this->userWith([]);
    $before = $this->api('GET', "/api/v1/users/{$u->id}");
    $this->api('PATCH', "/api/v1/users/{$u->id}", ['name' => 'Ada Obi'], [
        'If-Match' => $before->headers->get('ETag'), 'X-Correlation-Id' => 'corr-audit-0001', 'User-Agent' => 'FundlyTest/1.0', 'X-Device-Id' => 'device-42',
    ])->assertOk()->assertHeader('X-Correlation-Id', 'corr-audit-0001');

    $e = DB::table('audit_events')->where('action', 'access.user.updated')->where('entity_id', $u->id)->first();
    expect($e->actor_type)->toBe('user')
        ->and($e->actor_id)->toBe($this->tenant->admin()->id)
        ->and(json_decode($e->actor_roles, true))->toBe(['tenant_administrator'])
        ->and($e->effective_permissions_hash)->toMatch('/^[0-9a-f]{64}$/')
        ->and($e->source_ip)->toBe('127.0.0.1')
        ->and($e->user_agent)->toBe('FundlyTest/1.0')
        ->and($e->device_id)->toBe('device-42')
        ->and($e->correlation_id)->toBe('corr-audit-0001')
        ->and($e->permission)->toBe('user:manage')
        ->and(json_decode($e->before, true)['name'])->toBe('Test User')
        ->and(json_decode($e->after, true)['name'])->toBe('Ada Obi')
        ->and((new DateTimeImmutable($e->occurred_at))->getTimezone()->getName())->toBe('+00:00');
})->group('FR-AUD-001', 'FR-AUD-002');

it('masks PII in before/after values', function () {
    app(AuditTrail::class)->record(new AuditEntry('test.pii', entityType: 'party', entityId: 'p1',
        after: ['bvn' => '22345678991', 'phone' => '+2348031234567', 'password' => 'secret!', 'name' => 'Ada']));
    $after = json_decode(DB::table('audit_events')->where('action', 'test.pii')->value('after'), true);
    expect($after)->toBe(['bvn' => '2234*****91', 'name' => 'Ada', 'phone' => '+234********67', 'password' => '[REDACTED]']);
})->group('FR-AUD-002', 'FR-CMP-036');

it('attributes automated actions to named system identities', function () {
    app(AuditTrail::class)->record(new AuditEntry('system.tick'), Actor::system(SystemIdentity::Scheduler));
    $e = DB::table('audit_events')->where('action', 'system.tick')->first();
    expect($e->actor_type)->toBe('system')->and($e->actor_id)->toBe('system:scheduler');
    $installer = DB::table('audit_events')->where('action', 'platform.tenant.provisioned')->first();
    expect($installer->actor_id)->toBe('system:installer');
    expect(DB::table('audit_events')->where('actor_type', 'system')->where('actor_id', 'not like', 'system:%')->exists())->toBeFalse();
})->group('FR-AUD-005');

it('hash-chains events per tenant with a gap-free sequence', function () {
    $rows = DB::table('audit_events')->orderBy('seq')->get();
    expect($rows->count())->toBeGreaterThan(10);
    $prev = str_repeat('0', 64);
    foreach ($rows as $i => $r) {
        expect((int) $r->seq)->toBe($i + 1)->and($r->prev_hash)->toBe($prev);
        $prev = $r->hash;
    }
    expect(app(AuditVerifier::class)->verify($this->tenant->id)->ok())->toBeTrue();
})->group('FR-AUD-004');

it('gives the runtime role INSERT and SELECT only on the audit trail', function () {
    $id = DB::table('audit_events')->value('id');
    expect(fn () => DB::transaction(fn () => DB::table('audit_events')->where('id', $id)->update(['action' => 'x'])))->toThrow(QueryException::class, 'permission denied')
        ->and(fn () => DB::transaction(fn () => DB::table('audit_events')->where('id', $id)->delete()))->toThrow(QueryException::class, 'permission denied')
        ->and(fn () => DB::transaction(fn () => DB::statement('truncate audit_events')))->toThrow(QueryException::class, 'permission denied')
        ->and(fn () => DB::transaction(fn () => DB::table('integration_calls')->delete()))->toThrow(QueryException::class, 'permission denied');
})->group('FR-AUD-003', 'FR-AUD-012');

it('logs permission changes, role assignments and failed authorisation attempts', function () {
    $nobody = $this->userWith([]);
    $this->login($nobody);
    $this->api('GET', '/api/v1/users')->assertForbidden();
    $denied = DB::table('audit_events')->where('action', 'authz.denied')->where('actor_id', $nobody->id)->first();
    expect($denied->outcome)->toBe('denied')->and($denied->permission)->toBe('user:read')->and($denied->reason_code)->toBe('permission_not_granted');
})->group('FR-AUD-007');

it('searches the audit trail with filters and cursor pagination', function () {
    $this->api('GET', '/api/v1/audit-events?filter[action]=auth.login.succeeded')->assertOk()->assertJsonPath('data.0.action', 'auth.login.succeeded');
    $page1 = $this->api('GET', '/api/v1/audit-events?page[size]=5')->assertOk();
    expect($page1->json('data'))->toHaveCount(5)->and($page1->json('meta.page.has_more'))->toBeTrue();
    $page2 = $this->api('GET', '/api/v1/audit-events?page[size]=5&page[after]='.$page1->json('meta.page.next_cursor'))->assertOk();
    expect($page2->json('data.0.seq'))->toBe($page1->json('data.4.seq') - 1);
    $this->api('GET', '/api/v1/audit-events?filter[from]=yesterday-ish')->assertStatus(422);

    $reader = $this->userWith([Permission::AuditRead]);
    $this->login($reader);
    $this->api('GET', '/api/v1/audit/verify')->assertForbidden();
})->group('FR-AUD-001');

it('verifies the chain from the CLI and the API, and writes checkpoints', function () {
    expect(Artisan::call('audit:checkpoint'))->toBe(0);
    $cp = DB::table('audit_checkpoints')->first();
    expect($cp)->not->toBeNull()->and($cp->hash)->toBe(DB::table('audit_events')->where('seq', $cp->seq)->value('hash'));
    expect(Artisan::call('audit:verify', ['--tenant' => $this->tenant->id]))->toBe(0);
    $this->api('GET', '/api/v1/audit/verify')->assertOk()->assertJsonPath('data.ok', true)->assertJsonPath('data.checkpoints_checked', 1);
    // checkpoints are append-only too
    expect(fn () => DB::transaction(fn () => DB::table('audit_checkpoints')->delete()))->toThrow(QueryException::class);
})->group('FR-AUD-004');

it('keeps audit retention independent of operational data: deleting business rows leaves their audit history', function () {
    $this->login($this->tenant->admin());
    $r = $this->api('POST', '/api/v1/sod-rules', ['kind' => 'permission_pair', 'left' => 'config:author', 'right' => 'config:review', 'description' => 'temp'])->json('data');
    $this->api('DELETE', "/api/v1/sod-rules/{$r['id']}")->assertNoContent();
    expect(DB::table('sod_rules')->where('id', $r['id'])->exists())->toBeFalse()
        ->and(DB::table('audit_events')->where('entity_id', $r['id'])->pluck('action')->all())->toBe(['access.sod_rule.created', 'access.sod_rule.deleted']);
    expect(config('fundly.audit.retention_years'))->toBeGreaterThanOrEqual(5);
})->group('FR-AUD-012');

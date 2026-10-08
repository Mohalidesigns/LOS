<?php

declare(strict_types=1);

use Fundly\Modules\Access\Domain\Permission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->tenant = $this->provisionTenant();
    $this->login($this->tenant->admin());
});

it('accepts or generates a correlation id and echoes it on every response, including errors', function () {
    $this->api('GET', '/api/v1/me', [], ['X-Correlation-Id' => 'client-supplied-123'])->assertHeader('X-Correlation-Id', 'client-supplied-123');
    $generated = $this->api('GET', '/api/v1/me')->headers->get('X-Correlation-Id');
    expect($generated)->toMatch('/^[0-9a-f-]{36}$/');
    $err = $this->api('GET', '/api/v1/users/00000000-0000-7000-8000-000000000000', [], ['X-Correlation-Id' => 'err-corr-0001']);
    $err->assertNotFound()->assertHeader('X-Correlation-Id', 'err-corr-0001')->assertJsonPath('correlation_id', 'err-corr-0001');
    // malformed ids are replaced, not echoed
    // an unsafe value is replaced by a generated id (assert the shape: a random UUID can itself contain the hex "bad")
    expect($this->api('GET', '/api/v1/me', [], ['X-Correlation-Id' => "bad\nvalue"])->headers->get('X-Correlation-Id'))->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/');
})->group('FR-CBA-016');

it('requires an Idempotency-Key on effectful POSTs, replays the stored response, and rejects key reuse with another body', function () {
    $body = ['code' => 'teller', 'name' => 'Teller'];
    $this->api('POST', '/api/v1/roles', $body, ['Idempotency-Key' => ''])->assertStatus(422)->assertJsonPath('code', 'validation-failed');

    $first = $this->api('POST', '/api/v1/roles', $body, ['Idempotency-Key' => 'idem-key-0001'])->assertCreated();
    $replay = $this->api('POST', '/api/v1/roles', $body, ['Idempotency-Key' => 'idem-key-0001'])->assertCreated();
    expect($replay->json('data.id'))->toBe($first->json('data.id'))
        ->and($replay->headers->get('Idempotent-Replayed'))->toBe('true')
        ->and(DB::table('roles')->where('code', 'teller')->count())->toBe(1);

    $this->api('POST', '/api/v1/roles', ['code' => 'teller2', 'name' => 'Other'], ['Idempotency-Key' => 'idem-key-0001'])
        ->assertStatus(409)->assertJsonPath('code', 'idempotency-key-reused');
    // keys are per principal: another user may use the same key independently
    $other = $this->userWith([Permission::RoleManage]);
    $this->login($other);
    $this->api('POST', '/api/v1/roles', ['code' => 'teller3', 'name' => 'Third'], ['Idempotency-Key' => 'idem-key-0001'])->assertCreated();
})->group('FR-CBA-007');

it('does not store failed (5xx) responses, so the client may retry with the same key', function () {
    Route::middleware(['api', 'auth:sanctum', 'principal', 'authz:authenticated', 'idempotent'])->post('/api/v1/__boom', fn () => throw new RuntimeException('boom'));
    $this->validateOpenApi = false;
    $this->api('POST', '/api/v1/__boom', [], ['Idempotency-Key' => 'boom-key-0001'])->assertStatus(500)->assertJsonPath('code', 'internal-error')->assertJsonMissingPath('trace');
    expect(DB::table('idempotency_keys')->where('key', 'boom-key-0001')->exists())->toBeFalse();
})->group('FR-CBA-007');

it('renders all errors as RFC 9457 problem documents', function () {
    $r = $this->api('POST', '/api/v1/roles', ['code' => 'X Y', 'name' => ''])->assertStatus(422);
    expect($r->headers->get('Content-Type'))->toBe('application/problem+json')
        ->and($r->json('type'))->toBe('urn:fundly:problem:validation-failed')
        ->and($r->json('errors'))->toHaveKeys(['code', 'name'])
        ->and($r->json('instance'))->toBe('/api/v1/roles');
    $this->validateOpenApi = false; // undocumented on purpose
    $this->api('GET', '/api/v1/nope')->assertNotFound()->assertJsonPath('code', 'not-found');
    $this->api('DELETE', '/api/v1/me')->assertStatus(405)->assertJsonPath('code', 'method-not-allowed');
})->group('FR-SEC-019');

it('paginates lists with an opaque cursor and caps the page size', function () {
    foreach (range(1, 7) as $i) {
        $this->userWith([]);
    }
    $p1 = $this->api('GET', '/api/v1/users?page[size]=3')->assertOk();
    $p2 = $this->api('GET', '/api/v1/users?page[size]=3&page[after]='.$p1->json('meta.page.next_cursor'))->assertOk();
    expect(array_intersect(array_column($p1->json('data'), 'id'), array_column($p2->json('data'), 'id')))->toBe([]);
    $this->api('GET', '/api/v1/users?page[size]=101')->assertStatus(422);
    $this->api('GET', '/api/v1/users?page[after]=not-a-cursor')->assertStatus(422);
});

it('requires If-Match on PATCH: 428 when missing, 412 when stale', function () {
    $u = $this->userWith([]);
    $etag = $this->api('GET', "/api/v1/users/{$u->id}")->assertHeader('ETag')->headers->get('ETag');
    $this->api('PATCH', "/api/v1/users/{$u->id}", ['name' => 'A'])->assertStatus(428)->assertJsonPath('code', 'precondition-required');
    $this->api('PATCH', "/api/v1/users/{$u->id}", ['name' => 'A'], ['If-Match' => $etag])->assertOk();
    $this->api('PATCH', "/api/v1/users/{$u->id}", ['name' => 'B'], ['If-Match' => $etag])->assertStatus(412)->assertJsonPath('code', 'precondition-failed');
});

it('exposes liveness and readiness probes that report RLS enforcement', function () {
    $this->api('GET', '/health')->assertOk()->assertJsonPath('status', 'ok');
    $this->api('GET', '/ready')->assertOk()->assertJsonPath('checks.rls_enforced', 'ok')->assertJsonPath('checks.database', 'ok');
});

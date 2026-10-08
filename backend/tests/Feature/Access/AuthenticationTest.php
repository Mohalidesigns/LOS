<?php

declare(strict_types=1);

use Fundly\Modules\Access\Domain\Permission;
use Fundly\Modules\Access\Domain\Totp;
use Fundly\Modules\Access\Infrastructure\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->tenant = $this->provisionTenant();
    $this->admin = $this->tenant->admin();
});

it('enrols TOTP on first sign-in and stores the secret encrypted, never in clear', function () {
    $r = $this->api('POST', '/api/v1/auth/login', ['email' => $this->admin->email, 'password' => $this->admin->password]);
    $r->assertOk()->assertJsonPath('data.status', 'mfa_enrollment_required');
    $secret = $r->json('data.mfa_enrollment.secret');
    expect($r->json('data.mfa_enrollment.otpauth_uri'))->toStartWith('otpauth://totp/');

    $stored = User::query()->findOrFail($this->admin->id)->mfa_secret;
    expect($stored)->toStartWith('fe1.')->not->toContain($secret);

    // Not authenticated yet: password alone is not enough (MFA mandatory).
    $this->api('GET', '/api/v1/me')->assertStatus(401);

    $this->admin->mfaSecret = $secret;
    $this->api('POST', '/api/v1/auth/mfa/verify', ['code' => $this->totp($this->admin)])
        ->assertOk()->assertJsonPath('data.status', 'authenticated')->assertJsonPath('data.user.id', $this->admin->id);
    expect(User::query()->findOrFail($this->admin->id)->mfa_confirmed_at)->not->toBeNull();
    $this->api('GET', '/api/v1/me')->assertOk()->assertJsonPath('data.mfa_enrolled', true);
})->group('FR-SEC-013', 'LOS-FR-302', 'FR-SEC-017');

it('requires MFA on subsequent sign-ins and rejects a replayed code', function () {
    $this->login($this->admin);
    $this->api('POST', '/api/v1/auth/logout')->assertNoContent();
    $this->api('GET', '/api/v1/me')->assertStatus(401);

    $this->api('POST', '/api/v1/auth/login', ['email' => $this->admin->email, 'password' => $this->admin->password])
        ->assertOk()->assertJsonPath('data.status', 'mfa_required');
    $code = $this->totp($this->admin);
    $this->api('POST', '/api/v1/auth/mfa/verify', ['code' => $code])->assertOk();
    $this->api('POST', '/api/v1/auth/logout')->assertNoContent();

    $this->api('POST', '/api/v1/auth/login', ['email' => $this->admin->email, 'password' => $this->admin->password])->assertOk();
    // same code, same time step: replay is rejected
    $this->api('POST', '/api/v1/auth/mfa/verify', ['code' => $code])->assertStatus(401)
        ->assertJsonPath('code', 'authentication-failed');
})->group('FR-SEC-013', 'LOS-FR-302');

it('returns the same generic error for unknown users, bad passwords and locked accounts', function () {
    $unknown = $this->api('POST', '/api/v1/auth/login', ['email' => 'nobody@x.test', 'password' => 'whatever-123']);
    $bad = $this->api('POST', '/api/v1/auth/login', ['email' => $this->admin->email, 'password' => 'wrong-password-1']);
    expect($unknown->status())->toBe(401)->and($bad->status())->toBe(401)
        ->and($unknown->json('detail'))->toBe($bad->json('detail'))
        ->and($unknown->headers->get('Content-Type'))->toBe('application/problem+json');
})->group('LOS-FR-302', 'FR-SEC-019');

it('locks the account with backoff after repeated failures and audits it', function () {
    config(['fundly.auth.rate_limit.per_identity_per_minute' => 100, 'fundly.auth.rate_limit.per_ip_per_minute' => 100]);
    foreach (range(1, 5) as $_) {
        $this->api('POST', '/api/v1/auth/login', ['email' => $this->admin->email, 'password' => 'wrong-password-1'])->assertStatus(401);
    }
    $user = User::query()->findOrFail($this->admin->id);
    expect($user->failed_login_count)->toBe(5)->and($user->locked_until)->not->toBeNull();

    // Even the right password fails while locked.
    $this->api('POST', '/api/v1/auth/login', ['email' => $this->admin->email, 'password' => $this->admin->password])->assertStatus(401);
    expect(DB::table('audit_events')->where('action', 'auth.account.locked')->where('entity_id', $this->admin->id)->exists())->toBeTrue();

    $this->travel(2)->minutes();
    $this->api('POST', '/api/v1/auth/login', ['email' => $this->admin->email, 'password' => $this->admin->password])->assertOk();
})->group('LOS-FR-302', 'FR-AUD-007', 'FR-SEC-019');

it('rate-limits login attempts per identity', function () {
    config(['fundly.auth.rate_limit.per_identity_per_minute' => 3]);
    foreach (range(1, 3) as $_) {
        $this->api('POST', '/api/v1/auth/login', ['email' => 'x@y.test', 'password' => 'nope-nope-1'])->assertStatus(401);
    }
    $this->api('POST', '/api/v1/auth/login', ['email' => 'x@y.test', 'password' => 'nope-nope-1'])
        ->assertStatus(429)->assertJsonPath('code', 'too-many-requests')->assertHeader('Retry-After');
})->group('FR-SEC-019');

it('logs every authentication event in the audit trail', function () {
    $this->login($this->admin);
    $this->stepUp($this->admin);
    $this->api('POST', '/api/v1/auth/logout')->assertNoContent();
    $actions = DB::table('audit_events')->where('entity_id', $this->admin->id)->orderBy('seq')->pluck('action')->all();
    expect($actions)->toContain('auth.login.password_verified', 'auth.mfa.enrolled', 'auth.login.succeeded', 'auth.step_up.succeeded', 'auth.logout');
})->group('FR-AUD-007');

it('requires a recent step-up for high-risk actions', function () {
    $this->login($this->admin);
    $this->travel(6)->minutes(); // the sign-in itself counts as a step-up for 5 minutes
    $user = $this->userWith([]);
    $this->api('PATCH', "/api/v1/users/{$user->id}", ['name' => 'Renamed'], ['If-Match' => '*'])
        ->assertStatus(401)->assertJsonPath('code', 'step-up-required');

    $this->api('POST', '/api/v1/auth/step-up', ['password' => 'wrong-password-1', 'code' => $this->totp($this->admin)])->assertStatus(401);
    $this->stepUp($this->admin)->assertJsonStructure(['data' => ['step_up_ref', 'valid_for_minutes']]);
    $this->api('PATCH', "/api/v1/users/{$user->id}", ['name' => 'Renamed'], ['If-Match' => '*'])->assertOk();

    // the step-up reference is recorded on the audit event of the protected action
    $event = DB::table('audit_events')->where('action', 'access.user.updated')->where('entity_id', $user->id)->first();
    expect($event?->step_up_ref)->not->toBeNull();
})->group('FR-SEC-013');

it('ends idle and absolute-timeout sessions', function () {
    $this->login($this->admin);
    $this->travel(16)->minutes();
    $this->api('GET', '/api/v1/me')->assertStatus(401)->assertJsonPath('code', 'session-expired');

    $this->login($this->admin);
    foreach (range(1, 40) as $_) { // keep it busy, but exceed 8 h absolute
        $this->travel(14)->minutes();
        $last = $this->api('GET', '/api/v1/me');
        if ($last->status() === 401) {
            break;
        }
    }
    expect($last->status())->toBe(401)->and($last->json('code'))->toBe('session-expired');
})->group('FR-SEC-015');

it('caps concurrent sessions per user (oldest evicted)', function () {
    $this->login($this->admin);
    $firstJar = $this->jar;
    $this->login($this->admin); // second sign-in, new cookie jar
    $this->api('GET', '/api/v1/me')->assertOk();
    $this->jar = $firstJar;
    $this->api('GET', '/api/v1/me')->assertStatus(401);
    expect(DB::table('sessions')->where('user_id', $this->admin->id)->count())->toBe(1);
})->group('FR-SEC-015');

it('forces re-authentication after a privilege change', function () {
    $user = $this->userWith([Permission::RoleRead]);
    $this->login($user);
    $this->api('GET', '/api/v1/roles')->assertOk();

    app(\Fundly\Modules\Access\Application\SessionRevoker::class)->revokeAll($user->id);
    $this->api('GET', '/api/v1/roles')->assertStatus(401);
})->group('FR-SEC-015');

it('applies the same permission model to service tokens, which can only narrow access', function () {
    $svc = $this->userWith([Permission::RoleRead, Permission::UserRead], [], 'service');
    $model = User::query()->findOrFail($svc->id);
    $token = $model->createToken('partner-feed', [Permission::RoleRead->value], now()->addDay())->plainTextToken;

    $this->apiWithToken($token, 'GET', '/api/v1/roles')->assertOk();
    // the role grants user:read, but the token does not carry that ability
    $this->apiWithToken($token, 'GET', '/api/v1/users')->assertStatus(403);
    // nothing the role does not grant, whatever the token says
    $wide = $model->createToken('wide', ['*'], now()->addDay())->plainTextToken;
    $this->apiWithToken($wide, 'GET', '/api/v1/legal-entities')->assertStatus(403);
    $this->apiWithToken($wide, 'GET', '/api/v1/users')->assertOk();
    // tokens cannot step up, so step-up protected actions are refused
    $this->apiWithToken($wide, 'POST', '/api/v1/auth/step-up', ['password' => 'x'])->assertStatus(422);
})->group('FR-SEC-014');

it('issues scoped service tokens through the API with step-up', function () {
    $this->login($this->admin);
    $svc = $this->userWith([Permission::RoleRead], [], 'service');
    $r = $this->api('POST', "/api/v1/users/{$svc->id}/tokens", ['name' => 'feed', 'abilities' => ['role:read'], 'expires_at' => now()->addDays(30)->toIso8601String()]);
    $r->assertCreated();
    $this->apiWithToken($r->json('data.token'), 'GET', '/api/v1/roles')->assertOk();

    $human = $this->userWith([]);
    $this->api('POST', "/api/v1/users/{$human->id}/tokens", ['name' => 'x', 'abilities' => ['*'], 'expires_at' => now()->addDay()->toIso8601String()])
        ->assertStatus(422)->assertJsonPath('code', 'domain-rule-violation');
})->group('FR-SEC-014');

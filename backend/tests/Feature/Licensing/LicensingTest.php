<?php

declare(strict_types=1);

use Fundly\Integration\Ports\Licensing\LicensingPort;
use Fundly\Integration\Ports\Licensing\SignedLicence;
use Fundly\Integration\Runtime\Outbox\DispatchOutboxJob;
use Fundly\Modules\Access\Domain\Permission;
use Fundly\Modules\Licensing\Domain\LicenceProblem;
use Fundly\Modules\Licensing\Http\Middleware\EnsureJobLicensed;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->tenant = $this->provisionTenant();
});

function licenceFile(object $t, array $overrides = []): array
{
    $signed = $t->signLicence($overrides);

    return ['licence' => json_decode($signed->document, true), 'signature' => $signed->signature];
}

function replaceLicence(object $t, array $overrides): void
{
    // the runtime role cannot delete licences; install() supersedes the active one
    $t->installLicence($overrides);
    app()->forgetScopedInstances();
    $t->useTenant($t->tenant);
}

it('reports licence status with the installation fingerprint', function () {
    $this->login($this->tenant->admin());
    $r = $this->api('GET', '/api/v1/licence')->assertOk();
    expect($r->json('data.state'))->toBe('valid')
        ->and($r->json('data.installation_fingerprint'))->toBe(app(LicensingPort::class)->installationFingerprint())
        ->and($r->json('data.licence.modules'))->toBe(['core']);
    $this->api('POST', '/api/v1/licence/activation-request')->assertOk()->assertJsonStructure(['data' => ['installation_uuid', 'installation_fingerprint']]);
})->group('LOS-FR-316');

it('imports a signed licence only through maker-checker, verifying the Ed25519 signature both times', function () {
    [$maker, $checker] = [$this->tenant->admin(0), $this->tenant->admin(1)];
    $this->login($maker);
    $file = licenceFile($this, ['max_named_users' => 250, 'valid_to' => now()->addYears(2)->toAtomString()]);

    $tampered = $file;
    $tampered['licence']['max_named_users'] = 100000;
    $this->api('POST', '/api/v1/licence/import', $tampered)->assertStatus(422)->assertJsonPath('code', 'licence-signature-invalid');

    $cr = $this->api('POST', '/api/v1/licence/import', $file + ['reason' => 'renewal'])->assertStatus(202)->json('data');
    expect($cr['action_type'])->toBe('licensing.licence.import');
    $this->api('GET', '/api/v1/licence')->assertJsonPath('data.named_users.max', 100); // unchanged until approved

    $this->login($checker);
    $this->api('POST', "/api/v1/change-requests/{$cr['id']}/actions/approve")->assertOk()->assertJsonPath('data.status', 'executed');
    $this->api('GET', '/api/v1/licence')->assertJsonPath('data.named_users.max', 250)->assertJsonPath('data.licence.licence_id', $file['licence']['licence_id']);
    expect(DB::table('licences')->where('status', 'active')->count())->toBe(1)
        ->and(DB::table('licence_events')->where('event', 'imported')->exists())->toBeTrue()
        ->and(DB::table('audit_events')->where('action', 'licensing.licence.imported')->value('actor_id'))->toBe($checker->id);
})->group('LOS-FR-316', 'FR-SEC-007');

it('rejects a licence issued for another installation', function () {
    $this->login($this->tenant->admin());
    $this->api('POST', '/api/v1/licence/import', licenceFile($this, ['installation_fingerprint' => str_repeat('a', 64)]))
        ->assertStatus(422)->assertJsonPath('code', 'licence-installation-mismatch');
})->group('LOS-FR-316');

it('voids a stored licence whose row was tampered with', function () {
    $doc = json_decode(DB::table('licences')->where('status', 'active')->value('document'), true);
    $doc['max_named_users'] = 99999;
    DB::table('licences')->where('status', 'active')->update(['document' => json_encode($doc)]);
    app()->forgetScopedInstances();
    expect(app(LicensingPort::class)->currentLicence())->toBeNull();
})->group('LOS-FR-316');

it('allows operation in grace with a warning header, and blocks after grace except fail-safe routes', function () {
    replaceLicence($this, ['valid_from' => now()->subYear()->toAtomString(), 'valid_to' => now()->subDays(5)->toAtomString(), 'grace_days' => 30]);
    $this->login($this->tenant->admin());
    $this->api('GET', '/api/v1/roles')->assertOk()->assertHeader('Fundly-Licence-State', 'grace');

    replaceLicence($this, ['valid_from' => now()->subYear()->toAtomString(), 'valid_to' => now()->subDays(40)->toAtomString(), 'grace_days' => 30]);
    $auditor = $this->userWith([Permission::AuditRead, Permission::LicenceRead, Permission::RoleRead]);
    $this->login($auditor); // read-only principals can always sign in (D-034)
    $this->api('GET', '/api/v1/roles')->assertForbidden()->assertJsonPath('code', 'licence-expired');
    $this->api('GET', '/api/v1/audit-events')->assertOk()->assertHeader('Fundly-Licence-State', 'expired');
    $this->api('GET', '/api/v1/licence')->assertOk()->assertJsonPath('data.state', 'expired');
    expect(DB::table('audit_events')->where('action', 'licensing.enforcement.blocked')->exists())->toBeTrue();

    // an operational user cannot sign in beyond grace
    $operator = $this->userWith([Permission::RoleManage]);
    $this->api('POST', '/api/v1/auth/login', ['email' => $operator->email, 'password' => $operator->password])->assertForbidden()->assertJsonPath('code', 'licence-expired');
})->group('LOS-FR-316', 'FR-AUD-007');

it('recovers from expiry: CLI raises the import request, a checker approves through the exempt route', function () {
    [$maker, $checker] = [$this->tenant->admin(0), $this->tenant->admin(1)];
    $this->login($checker);
    $checkerJar = $this->jar;
    replaceLicence($this, ['valid_from' => now()->subYear()->toAtomString(), 'valid_to' => now()->subDays(40)->toAtomString(), 'grace_days' => 30]);

    $path = storage_path('framework/testing/renewal.lic');
    file_put_contents($path, $this->signLicence(['valid_to' => now()->addYear()->toAtomString()])->toFileContents());
    expect(Artisan::call('licence:import', ['file' => $path, '--maker' => $maker->email, '--tenant' => $this->tenant->id]))->toBe(0);
    $this->useTenant($this->tenant);
    $crId = DB::table('change_requests')->where('action_type', 'licensing.licence.import')->value('id');

    $this->jar = $checkerJar;
    $this->api('GET', '/api/v1/roles')->assertForbidden(); // still expired
    $this->api('POST', "/api/v1/change-requests/{$crId}/actions/approve")->assertOk()->assertJsonPath('data.status', 'executed');
    $this->api('GET', '/api/v1/roles')->assertOk()->assertHeader('Fundly-Licence-State', 'valid');
    @unlink($path);
})->group('LOS-FR-316');

it('enforces module entitlement per route group', function () {
    Route::middleware(['api', 'auth:sanctum', 'principal', 'licence:origination', 'authz:authenticated'])->get('/api/v1/__origination-probe', fn () => response()->json(['ok' => true]));
    $this->validateOpenApi = false;
    $this->login($this->tenant->admin());
    $this->api('GET', '/api/v1/__origination-probe')->assertForbidden()->assertJsonPath('code', 'licence-module-not-entitled');
    replaceLicence($this, ['modules' => ['core', 'origination']]);
    $this->api('GET', '/api/v1/__origination-probe')->assertOk();
})->group('LOS-FR-316');

it('enforces the named-user cap at user creation and at login', function () {
    replaceLicence($this, ['max_named_users' => 3]);
    $this->login($this->tenant->admin());
    $this->api('POST', '/api/v1/users', ['kind' => 'human', 'email' => 'third@bank.test', 'name' => 'Third', 'password' => 'Str0ng!Passw0rd'])->assertCreated();
    $this->api('POST', '/api/v1/users', ['kind' => 'human', 'email' => 'fourth@bank.test', 'name' => 'Fourth', 'password' => 'Str0ng!Passw0rd'])
        ->assertForbidden()->assertJsonPath('code', 'licence-user-cap-reached');
    // service accounts are not named users
    $this->api('POST', '/api/v1/users', ['kind' => 'service', 'email' => 'feed@bank.test', 'name' => 'Feed'])->assertCreated();

    // if the cap is lowered below the active population, ordinary users cannot sign in
    replaceLicence($this, ['max_named_users' => 2]);
    $u = $this->userWith([Permission::RoleManage]); // not read-only, so not fail-safe
    $this->api('POST', '/api/v1/auth/login', ['email' => $u->email, 'password' => $u->password])->assertForbidden()->assertJsonPath('code', 'licence-user-cap-exceeded');
    // administrators (user:manage) can still sign in to deactivate users
    $this->login($this->tenant->admin());
})->group('LOS-FR-316');

it('blocks ordinary queued jobs when the licence is invalid but never fail-safe ones', function () {
    DB::table('licences')->update(['status' => 'superseded']);
    app()->forgetScopedInstances();
    $this->useTenant($this->tenant);
    $mw = app(EnsureJobLicensed::class);
    expect(fn () => $mw->handle(new stdClass, fn () => true))->toThrow(LicenceProblem::class)
        ->and($mw->handle(new DispatchOutboxJob, fn () => true))->toBeTrue();
})->group('LOS-FR-316');

it('provides dev-only keypair and issue commands that produce a verifiable licence, and refuses them in production', function () {
    $dir = storage_path('framework/testing');
    @unlink("{$dir}/vendor.key");
    expect(Artisan::call('licence:keypair', ['--secret-out' => "{$dir}/vendor.key"]))->toBe(0);
    preg_match('/FUNDLY_LICENCE_PUBLIC_KEY=(\S+)/', Artisan::output(), $m);
    config(['fundly.licence.public_key' => $m[1]]);
    app()->forgetScopedInstances();
    $this->useTenant($this->tenant);
    expect(Artisan::call('licence:issue', ['--secret-key-file' => "{$dir}/vendor.key", '--out' => "{$dir}/dev.lic", '--modules' => 'core,origination']))->toBe(0);
    $licence = app(LicensingPort::class)->verify(SignedLicence::fromFileContents((string) file_get_contents("{$dir}/dev.lic")));
    expect($licence->modules)->toBe(['core', 'origination']);

    config(['fundly.installation.environment' => 'production']);
    expect(Artisan::call('licence:keypair'))->toBe(1);
    @unlink("{$dir}/vendor.key");
    @unlink("{$dir}/dev.lic");
})->group('LOS-FR-316');

it('raises expiry warnings into the audit trail', function () {
    replaceLicence($this, ['valid_to' => now()->addDays(30)->addHour()->toAtomString()]);
    expect(Artisan::call('licence:check'))->toBe(0);
    $this->useTenant($this->tenant);
    expect(DB::table('audit_events')->where('action', 'licensing.licence.expiry_warning_t_minus_30')->value('actor_id'))->toBe('system:licensing');
})->group('LOS-FR-316');

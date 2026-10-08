<?php

declare(strict_types=1);

namespace Tests;

use Fundly\Integration\Adapters\LocalKeyfile\LocalKeyfileKms;
use Fundly\Integration\Ports\Licensing\LicensingPort;
use Fundly\Integration\Ports\Licensing\SignedLicence;
use Fundly\Modules\Access\Domain\Permission;
use Fundly\Modules\Access\Domain\Totp;
use Fundly\Modules\Access\Infrastructure\Models\Role;
use Fundly\Modules\Access\Infrastructure\Models\RoleAssignment;
use Fundly\Modules\Access\Infrastructure\Models\RolePermission;
use Fundly\Modules\Access\Infrastructure\Models\User;
use Fundly\Modules\Platform\Application\Provisioning\TenantProvisioner;
use Fundly\Shared\Id\UuidV7;
use Fundly\Shared\Json\CanonicalJson;
use Fundly\Shared\Tenancy\TenantContext;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\OpenApiValidator;
use Tests\Support\TenantFixture;
use Tests\Support\UserCredentials;

abstract class TestCase extends BaseTestCase
{
    public const PASSWORD = 'Correct-Horse-Battery-9';

    /** @var array{public: string, secret: string}|null */
    private static ?array $licenceKeys = null;

    /** @var array<string, string> cookie jar for stateful SPA requests */
    protected array $jar = [];

    protected ?TenantFixture $currentTenant = null;

    protected bool $validateOpenApi = true;

    public function createApplication()
    {
        $app = require __DIR__.'/../bootstrap/app.php';
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        // Per-run key material: nothing secret is committed.
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat("\x01", 32)));
        self::$licenceKeys ??= (static function (): array {
            $pair = sodium_crypto_sign_keypair();

            return ['public' => base64_encode(sodium_crypto_sign_publickey($pair)), 'secret' => base64_encode(sodium_crypto_sign_secretkey($pair))];
        })();
        $app['config']->set('fundly.licence.public_key', self::$licenceKeys['public']);
        $kek = base_path((string) $app['config']->get('fundly.crypto.kek_path'));
        if (! is_file($kek)) {
            LocalKeyfileKms::generateKeyFile($kek);
        }

        return $app;
    }

    protected function tearDown(): void
    {
        $this->jar = [];
        $this->currentTenant = null;
        parent::tearDown();
    }

    // ---- tenancy & fixtures ------------------------------------------------------------------

    public function tenantContext(): TenantContext
    {
        return $this->app->make(TenantContext::class);
    }

    public function useTenant(TenantFixture|string $tenant): void
    {
        $id = $tenant instanceof TenantFixture ? $tenant->id : $tenant;
        if ($tenant instanceof TenantFixture) {
            $this->currentTenant = $tenant;
        }
        $this->tenantContext()->set($id);
    }

    /** Provision a tenant through the real provisioner (role library, SoD rules, two administrators). */
    public function provisionTenant(?string $slug = null): TenantFixture
    {
        $slug ??= 'bank-'.Str::lower(Str::random(6));
        $host = $slug.'.test';
        $emails = ["admin1@{$slug}.test", "admin2@{$slug}.test"];
        $result = $this->app->make(TenantProvisioner::class)->provision($slug, 'Bank '.$slug, [
            ['email' => $emails[0], 'name' => 'First Admin', 'password' => self::PASSWORD],
            ['email' => $emails[1], 'name' => 'Second Admin', 'password' => self::PASSWORD],
        ], $host);
        $admins = [];
        foreach ($result['admin_ids'] as $i => $id) {
            $admins[] = new UserCredentials($id, $emails[$i], self::PASSWORD, $result['tenant_id']);
        }
        $fixture = new TenantFixture($result['tenant_id'], $slug, $host, $admins);
        $this->useTenant($fixture);

        return $fixture;
    }

    /**
     * Test fixture shortcut: a user holding a role with exactly these permissions
     * (bypasses maker-checker; real flows are covered by their own tests).
     *
     * @param  list<string|Permission>  $permissions
     * @param  array<string, mixed>  $scope
     */
    public function userWith(array $permissions, array $scope = [], string $kind = 'human', ?string $homeOrgUnitId = null, ?string $homeLegalEntityId = null): UserCredentials
    {
        $tenant = $this->tenantContext()->requireId();
        $role = new Role;
        $role->forceFill(['code' => 'test_'.Str::lower(Str::random(10)), 'name' => 'Test role', 'is_template' => false])->save();
        foreach ($permissions as $p) {
            (new RolePermission)->forceFill(['role_id' => $role->id, 'permission_code' => $p instanceof Permission ? $p->value : $p])->save();
        }
        $email = Str::lower(Str::random(10)).'@staff.test';
        $user = new User;
        $user->forceFill([
            'kind' => $kind,
            'email' => $email,
            'name' => 'Test User',
            'password' => $kind === 'human' ? $this->app->make(Hasher::class)->make(self::PASSWORD) : null,
            'status' => 'active',
            'home_org_unit_id' => $homeOrgUnitId,
            'home_legal_entity_id' => $homeLegalEntityId,
        ])->save();
        (new RoleAssignment)->forceFill([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope' => \Fundly\Modules\Access\Domain\Scope::fromArray($scope)->toArray(),
            'valid_from' => now()->subMinute(),
            'granted_by' => 'test-fixture',
        ])->save();

        return new UserCredentials($user->id, $email, self::PASSWORD, $tenant);
    }

    public function installLicence(array $overrides = []): void
    {
        $port = $this->app->make(LicensingPort::class);
        $port->install($this->signLicence($overrides), null, null, null);
    }

    /** @param array<string, mixed> $overrides */
    public function signLicence(array $overrides = []): SignedLicence
    {
        $port = $this->app->make(LicensingPort::class);
        $licence = array_merge([
            'licence_id' => 'LIC-'.UuidV7::generate(),
            'client' => 'Test Bank Plc',
            'installation_fingerprint' => $port->installationFingerprint(),
            'edition' => 'standard',
            'modules' => ['core'],
            'max_named_users' => 100,
            'max_legal_entities' => 5,
            'adapter_entitlements' => ['cba-simulator'],
            'valid_from' => now()->subDay()->toAtomString(),
            'valid_to' => now()->addYear()->toAtomString(),
            'grace_days' => 30,
            'support_tier' => 'standard',
        ], $overrides);
        $document = CanonicalJson::encode($licence);
        $secret = base64_decode((string) self::$licenceKeys['secret'], true);

        return new SignedLicence($document, base64_encode(sodium_crypto_sign_detached($document, (string) $secret)));
    }

    /**
     * LE "ABC" with labels Region/Area/Branch, regions NORTH and SOUTH, and KANO under NORTH,
     * created through the API (caller must be signed in as an administrator).
     *
     * @return array<string, array<string, mixed>>
     */
    public function createOrgTree(): array
    {
        $le = $this->api('POST', '/api/v1/legal-entities', [
            'code' => 'ABC', 'name' => 'ABC Bank Plc', 'jurisdiction' => 'NG', 'licence_category' => 'commercial_bank',
            'base_currency' => 'NGN', 'timezone' => 'Africa/Lagos', 'org_level_labels' => ['Region', 'Area', 'Branch'],
        ])->assertCreated()->json('data');
        $north = $this->api('POST', '/api/v1/org-units', ['legal_entity_id' => $le['id'], 'code' => 'NORTH', 'name' => 'North'])->assertCreated()->json('data');
        $south = $this->api('POST', '/api/v1/org-units', ['legal_entity_id' => $le['id'], 'code' => 'SOUTH', 'name' => 'South'])->assertCreated()->json('data');
        $kano = $this->api('POST', '/api/v1/org-units', ['legal_entity_id' => $le['id'], 'parent_id' => $north['id'], 'code' => 'KANO', 'name' => 'Kano'])->assertCreated()->json('data');

        return compact('le', 'north', 'south', 'kano');
    }

    // ---- HTTP ---------------------------------------------------------------------------------

    /**
     * A stateful SPA request (Referer from a Sanctum stateful domain, cookie jar
     * carried between requests). POSTs get an Idempotency-Key unless given.
     * Every /api/v1 response is validated against the OpenAPI document.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $headers
     */
    public function api(string $method, string $uri, array $data = [], array $headers = [], ?TenantFixture $tenant = null): TestResponse
    {
        $tenant ??= $this->currentTenant;
        $method = strtoupper($method);
        $headers += ['Referer' => 'http://localhost/', 'Accept' => 'application/json'];
        if ($method === 'POST' && ! array_key_exists('Idempotency-Key', $headers)) {
            $headers['Idempotency-Key'] = 'test-'.UuidV7::generate();
        }
        if (($headers['Idempotency-Key'] ?? null) === '') {
            unset($headers['Idempotency-Key']);
        }
        $url = str_starts_with($uri, 'http') ? $uri : 'http://'.($tenant?->host ?? 'localhost').'/'.ltrim($uri, '/');

        $previousTenant = $this->tenantContext()->id();
        // Like Octane between requests: fresh scoped services and controller instances.
        $this->app->forgetScopedInstances();
        foreach ($this->app['router']->getRoutes() as $route) {
            $route->flushController();
        }
        $this->app['auth']->forgetGuards();
        $this->app->forgetInstance('auth.driver');
        $this->app['session']->forgetDrivers();
        $this->app->forgetInstance('session.store');
        $this->flushHeaders();
        $response = $this->withCredentials()->withUnencryptedCookies($this->jar)->withHeaders($headers)->json($method, $url, $data);
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getValue() === null || $cookie->getValue() === '' || $cookie->isCleared()) {
                unset($this->jar[$cookie->getName()]);
            } else {
                $this->jar[$cookie->getName()] = $cookie->getValue();
            }
        }
        if ($previousTenant !== null) {
            $this->tenantContext()->set($previousTenant);
        }
        if ($this->validateOpenApi) {
            OpenApiValidator::instance()->assertResponseMatches($method, parse_url($url, PHP_URL_PATH) ?: '/', $response);
        }

        return $response;
    }

    /** Bearer-token request for service/partner principals (no session). */
    public function apiWithToken(string $token, string $method, string $uri, array $data = [], array $headers = []): TestResponse
    {
        $saved = $this->jar;
        $this->jar = [];
        $headers += ['Authorization' => 'Bearer '.$token, 'Referer' => ''];
        $response = $this->api($method, $uri, $data, $headers);
        $this->jar = $saved;

        return $response;
    }

    /** Full sign-in: password, then TOTP (enrolling on first use). */
    public function login(UserCredentials $user, ?TenantFixture $tenant = null): TestResponse
    {
        $this->jar = [];
        $first = $this->api('POST', '/api/v1/auth/login', ['email' => $user->email, 'password' => $user->password], [], $tenant);
        $first->assertOk();
        $status = $first->json('data.status');
        if ($status === 'mfa_enrollment_required') {
            $user->mfaSecret = (string) $first->json('data.mfa_enrollment.secret');
        }
        if ($status === 'authenticated') {
            return $first;
        }
        $verify = $this->api('POST', '/api/v1/auth/mfa/verify', ['code' => $this->totp($user)], [], $tenant);
        $verify->assertOk()->assertJsonPath('data.status', 'authenticated');

        return $verify;
    }

    /** Current TOTP code; moves the clock one step forward so codes are never replayed. */
    public function totp(UserCredentials $user): string
    {
        $this->travel(31)->seconds();

        return Totp::codeAt((string) $user->mfaSecret, now()->getTimestamp());
    }

    public function stepUp(UserCredentials $user): TestResponse
    {
        return $this->api('POST', '/api/v1/auth/step-up', ['password' => $user->password, 'code' => $this->totp($user)])->assertOk();
    }
}

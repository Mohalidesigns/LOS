<?php

declare(strict_types=1);

namespace Fundly\Shared;

use Fundly\Integration\Adapters\LocalKeyfile\LocalKeyfileKms;
use Fundly\Integration\Ports\KeyManagement\KeyManagementPort;
use Fundly\Shared\Audit\ActorProvider;
use Fundly\Shared\Audit\AuditTrail;
use Fundly\Shared\Audit\AuditVerifier;
use Fundly\Shared\Audit\PostgresAuditTrail;
use Fundly\Shared\Bus\CommandBus;
use Fundly\Shared\Clock\Clock;
use Fundly\Shared\Clock\SystemClock;
use Fundly\Shared\Crypto\FieldEncryptor;
use Fundly\Shared\Crypto\TenantKeyRing;
use Fundly\Shared\Http\ProblemRenderer;
use Fundly\Shared\Http\RequestContext;
use Fundly\Shared\Idempotency\IdempotencyStore;
use Fundly\Shared\Outbox\DatabaseOutbox;
use Fundly\Shared\Outbox\Outbox;
use Fundly\Shared\Pii\PiiMasker;
use Fundly\Shared\Security\CurrentPrincipal;
use Fundly\Shared\Security\ResourceResolver;
use Fundly\Shared\Tenancy\TenantContext;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\ServiceProvider;

/** Shared kernel bindings (TRD §2.2). Request-scoped state is `scoped` so long-running workers stay clean. */
final class SharedServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Clock::class, SystemClock::class);
        $this->app->bind(ConnectionInterface::class, fn (Application $app) => $app->make('db')->connection());
        $this->app->scoped(RequestContext::class);
        $this->app->scoped(CurrentPrincipal::class);
        $this->app->scoped(TenantContext::class, fn (Application $app) => new TenantContext($app->make('db')->connection()));
        $this->app->singleton(ResourceResolver::class);
        $this->app->singleton(PiiMasker::class, fn () => new PiiMasker(
            array_values(array_map('strval', (array) config('fundly.pii.mask_keys', []))),
            array_values(array_map('strval', (array) config('fundly.pii.redact_keys', []))),
        ));
        $this->app->scoped(AuditTrail::class, fn (Application $app) => new PostgresAuditTrail(
            $app->make('db')->connection(),
            $app->make(TenantContext::class),
            $app->make(ActorProvider::class),
            $app->make(RequestContext::class),
            $app->make(PiiMasker::class),
            $app->make(Clock::class),
        ));
        $this->app->scoped(AuditVerifier::class);
        $this->app->scoped(Outbox::class, fn (Application $app) => new DatabaseOutbox(
            $app->make('db')->connection(),
            $app->make(TenantContext::class),
            $app->make(Clock::class),
            $app->make(RequestContext::class),
            $app->make(ActorProvider::class),
            (int) config('fundly.integration.retry.max_attempts', 5),
        ));
        $this->app->scoped(IdempotencyStore::class, fn (Application $app) => new IdempotencyStore(
            $app->make('db')->connection(),
            $app->make(TenantContext::class),
            $app->make(Clock::class),
            (int) config('fundly.api.idempotency_ttl_hours', 72),
        ));
        $this->app->scoped(CommandBus::class);
        $this->app->scoped(ProblemRenderer::class);
        $this->app->singleton(KeyManagementPort::class, fn () => LocalKeyfileKms::fromFile(
            self::absolutePath((string) config('fundly.crypto.kek_path')),
            (string) config('fundly.crypto.kek_id', 'local-1'),
        ));
        $this->app->scoped(TenantKeyRing::class);
        $this->app->scoped(FieldEncryptor::class);
    }

    public static function absolutePath(string $path): string
    {
        return str_starts_with($path, '/') ? $path : base_path($path);
    }
}

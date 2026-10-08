<?php

declare(strict_types=1);

namespace Fundly\Integration;

use Fundly\Integration\Adapters\Clamav\ClamdScanner;
use Fundly\Integration\Ports\CoreBanking\CoreBankingPort;
use Fundly\Integration\Ports\Identity\IdentityVerificationPort;
use Fundly\Integration\Ports\MalwareScan\MalwareScanPort;
use Fundly\Integration\Ports\Screening\ScreeningPort;
use Fundly\Integration\Runtime\AdapterDefinition;
use Fundly\Integration\Runtime\AdapterRegistry;
use Fundly\Integration\Runtime\BindingResolver;
use Fundly\Integration\Runtime\Console\BindSimulatorsCommand;
use Fundly\Integration\Runtime\Handlers\CreateLoanAccountHandler;
use Fundly\Integration\Runtime\Handlers\DisburseHandler;
use Fundly\Integration\Runtime\Models\AdapterBinding;
use Fundly\Integration\Runtime\Outbox\OutboxHandlerRegistry;
use Fundly\Integration\Runtime\Resilience\BreakerStore;
use Fundly\Integration\Runtime\Resilience\CircuitBreaker;
use Fundly\Integration\Runtime\Resilience\DatabaseBreakerStore;
use Fundly\Integration\Runtime\Resilience\RandomSource;
use Fundly\Integration\Runtime\Resilience\RealSleeper;
use Fundly\Integration\Runtime\Resilience\RedisBreakerStore;
use Fundly\Integration\Runtime\Resilience\RetryPolicy;
use Fundly\Integration\Runtime\Resilience\SecureRandom;
use Fundly\Integration\Runtime\Resilience\Sleeper;
use Fundly\Integration\Simulators\CoreBanking\CbaSimulator;
use Fundly\Integration\Simulators\CoreBanking\FaultScript;
use Fundly\Integration\Simulators\CoreBanking\SimulatorStore;
use Fundly\Integration\Simulators\Identity\IdentitySimulator;
use Fundly\Integration\Simulators\MalwareScan\MalwareScanSimulator;
use Fundly\Integration\Simulators\Screening\ScreeningSimulator;
use Fundly\Shared\Audit\AuditTrail;
use Fundly\Shared\Clock\Clock;
use Fundly\Shared\Http\RequestContext;
use Fundly\Shared\Tenancy\TenantContext;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class IntegrationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RandomSource::class, SecureRandom::class);
        $this->app->singleton(Sleeper::class, RealSleeper::class);
        $this->app->singleton(AdapterRegistry::class);
        $this->app->singleton(OutboxHandlerRegistry::class);
        $this->app->scoped(BindingResolver::class, fn (Application $app) => new BindingResolver($app->make(AdapterRegistry::class), (string) config('fundly.installation.environment')));
        $this->app->scoped(BreakerStore::class, fn (Application $app) => config('fundly.integration.breaker.store') === 'redis'
            ? new RedisBreakerStore($app->make('redis'), $app->make(TenantContext::class))
            : new DatabaseBreakerStore($app->make('db')->connection(), $app->make(TenantContext::class), $app->make(Clock::class)));
        $this->app->scoped(CircuitBreaker::class, fn (Application $app) => new CircuitBreaker(
            $app->make(BreakerStore::class),
            $app->make(Clock::class),
            $app->make('db')->connection(),
            $app->make(TenantContext::class),
            $app->make(AuditTrail::class),
            (int) config('fundly.integration.breaker.failure_threshold', 5),
            (int) config('fundly.integration.breaker.open_seconds', 30),
        ));
        $this->app->bind(RetryPolicy::class, fn (Application $app) => new RetryPolicy(
            (int) config('fundly.integration.retry.max_attempts', 5),
            (int) config('fundly.integration.retry.base_delay_ms', 500),
            (int) config('fundly.integration.retry.max_delay_ms', 300_000),
            $app->make(RandomSource::class),
        ));
    }

    public function boot(AdapterRegistry $adapters, OutboxHandlerRegistry $handlers): void
    {
        $app = $this->app;
        $adapters->register(new AdapterDefinition(
            key: CbaSimulator::KEY,
            port: CoreBankingPort::PORT,
            version: CbaSimulator::VERSION,
            manifest: CbaSimulator::capabilities(),
            factory: static function (AdapterBinding $binding) use ($app): CbaSimulator {
                $production = config('fundly.installation.environment') === 'production';

                return new CbaSimulator(
                    $app->make(SimulatorStore::class),
                    // Fault scripts are selectable in non-production only (register §3).
                    $production ? new FaultScript([]) : FaultScript::fromArray($binding->fault_script),
                    $app->make(Sleeper::class),
                    $app->make(RequestContext::class),
                    $binding->config,
                );
            },
            isSimulator: true,
        ));

        $adapters->register(new AdapterDefinition(
            key: IdentitySimulator::KEY,
            port: IdentityVerificationPort::PORT,
            version: IdentitySimulator::VERSION,
            manifest: IdentitySimulator::capabilities(),
            factory: static fn (AdapterBinding $binding): IdentitySimulator => new IdentitySimulator($binding->config),
            isSimulator: true,
        ));
        $adapters->register(new AdapterDefinition(
            key: ScreeningSimulator::KEY,
            port: ScreeningPort::PORT,
            version: ScreeningSimulator::VERSION,
            manifest: ScreeningSimulator::capabilities(),
            factory: static fn (AdapterBinding $binding): ScreeningSimulator => new ScreeningSimulator($binding->config),
            isSimulator: true,
        ));

        $adapters->register(new AdapterDefinition(
            key: MalwareScanSimulator::KEY,
            port: MalwareScanPort::PORT,
            version: MalwareScanSimulator::VERSION,
            manifest: MalwareScanSimulator::capabilities(),
            factory: static fn (AdapterBinding $binding): MalwareScanSimulator => new MalwareScanSimulator,
            isSimulator: true,
        ));
        $adapters->register(new AdapterDefinition(
            key: ClamdScanner::KEY,
            port: MalwareScanPort::PORT,
            version: ClamdScanner::VERSION,
            manifest: ClamdScanner::capabilities(),
            factory: static fn (AdapterBinding $binding): ClamdScanner => new ClamdScanner($binding->config),
        ));

        if ($this->app->runningInConsole()) {
            $this->commands([BindSimulatorsCommand::class]);
        }

        $handlers->register(CreateLoanAccountHandler::TOPIC, CreateLoanAccountHandler::class);
        $handlers->register(DisburseHandler::TOPIC, DisburseHandler::class);
    }
}

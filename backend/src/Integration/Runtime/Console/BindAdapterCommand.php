<?php

declare(strict_types=1);

namespace Fundly\Integration\Runtime\Console;

use Fundly\Integration\Runtime\AdapterRegistry;
use Fundly\Integration\Runtime\Errors\NonRetryableError;
use Fundly\Integration\Runtime\Models\AdapterBinding;
use Fundly\Modules\Platform\Contracts\TenantDirectory;
use Fundly\Shared\Tenancy\TenantContext;
use Illuminate\Console\Command;

/**
 * Operator command: point a port at an adapter for every active tenant (the
 * previous tenant-wide binding is retired, not deleted). Credentials stay in
 * the environment; only non-secret settings may go in --config.
 */
final class BindAdapterCommand extends Command
{
    protected $signature = 'integration:bind {port} {adapter} {--config= : JSON of non-secret adapter settings}';

    protected $description = 'Bind a port to an adapter for every active tenant.';

    public function handle(AdapterRegistry $registry, TenantDirectory $tenants, TenantContext $tenant): int
    {
        $port = (string) $this->argument('port');
        $key = (string) $this->argument('adapter');
        try {
            $definition = $registry->get($key);
        } catch (NonRetryableError) {
            $this->error("Unknown adapter {$key}.");

            return self::FAILURE;
        }
        if ($definition->port !== $port) {
            $this->error("Adapter {$key} serves port {$definition->port}, not {$port}.");

            return self::FAILURE;
        }
        if ($definition->isSimulator && config('fundly.installation.environment') === 'production') {
            $this->error('Simulators cannot be bound in a production installation.');

            return self::FAILURE;
        }
        $config = json_decode((string) ($this->option('config') ?? '{}'), true);
        if (! is_array($config)) {
            $this->error('--config must be a JSON object.');

            return self::FAILURE;
        }
        foreach ($tenants->activeTenantIds() as $tenantId) {
            $tenant->run($tenantId, function () use ($definition, $port, $config): void {
                $current = AdapterBinding::query()->where('port', $port)->where('status', 'active')->whereNull('legal_entity_id')->first();
                if ($current !== null && $current->adapter_key === $definition->key && $current->adapter_version === $definition->version && $current->config == $config) {
                    $this->info("{$port} already bound to {$definition->key}");

                    return;
                }
                $current?->forceFill(['status' => 'retired'])->save();
                AdapterBinding::query()->create([
                    'port' => $port, 'adapter_key' => $definition->key, 'adapter_version' => $definition->version,
                    'config' => $config, 'processing_location' => $definition->manifest->processingLocation, 'status' => 'active',
                ]);
                $this->info("Bound {$port} → {$definition->key}");
            });
        }

        return self::SUCCESS;
    }
}

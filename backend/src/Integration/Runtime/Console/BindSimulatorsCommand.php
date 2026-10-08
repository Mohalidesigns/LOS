<?php

declare(strict_types=1);

namespace Fundly\Integration\Runtime\Console;

use Fundly\Integration\Runtime\AdapterRegistry;
use Fundly\Integration\Runtime\Models\AdapterBinding;
use Fundly\Modules\Platform\Contracts\TenantDirectory;
use Fundly\Shared\Tenancy\TenantContext;
use Illuminate\Console\Command;

/**
 * Non-production convenience: give every port that has no active binding its
 * simulator (UAT / demo installs, D-037). Refused in production installations.
 */
final class BindSimulatorsCommand extends Command
{
    protected $signature = 'integration:bind-simulators';

    protected $description = '[non-production] Bind every unbound port to its simulator adapter.';

    public function handle(AdapterRegistry $registry, TenantDirectory $tenants, TenantContext $tenant): int
    {
        if (config('fundly.installation.environment') === 'production') {
            $this->error('Simulators cannot be bound in a production installation.');

            return self::FAILURE;
        }
        foreach ($tenants->activeTenantIds() as $tenantId) {
            $tenant->run($tenantId, function () use ($registry): void {
                foreach ($registry->all() as $definition) {
                    if (! $definition->isSimulator || AdapterBinding::query()->where('port', $definition->port)->where('status', 'active')->exists()) {
                        continue;
                    }
                    AdapterBinding::query()->create([
                        'port' => $definition->port, 'adapter_key' => $definition->key, 'adapter_version' => $definition->version,
                        'config' => [], 'processing_location' => $definition->manifest->processingLocation, 'status' => 'active',
                    ]);
                    $this->info("Bound {$definition->port} → {$definition->key}");
                }
            });
        }

        return self::SUCCESS;
    }
}

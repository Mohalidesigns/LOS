<?php

declare(strict_types=1);

namespace Fundly\Integration\Runtime;

use Fundly\Integration\Runtime\Errors\NonRetryableError;
use Fundly\Integration\Runtime\Models\AdapterBinding;

/**
 * Resolves the active binding for a port (legal-entity specific first, then
 * tenant-wide) and refuses a simulator in a production installation unless
 * the binding carries the explicit, audited override (register §3).
 */
final class BindingResolver
{
    public function __construct(private readonly AdapterRegistry $registry, private readonly string $installationEnvironment)
    {
    }

    public function resolve(string $port, ?string $legalEntityId = null): AdapterBinding
    {
        $query = AdapterBinding::query()->where('port', $port)->where('status', 'active');
        $binding = null;
        if ($legalEntityId !== null) {
            $binding = (clone $query)->where('legal_entity_id', $legalEntityId)->first();
        }
        $binding ??= $query->whereNull('legal_entity_id')->first();
        if (! $binding instanceof AdapterBinding) {
            throw new NonRetryableError('INTEGRATION.BINDING.MISSING', "No active adapter binding for port {$port}.");
        }

        $definition = $this->registry->get($binding->adapter_key);
        if ($definition->isSimulator && $this->isProduction() && ! $binding->allow_in_production) {
            throw new NonRetryableError('INTEGRATION.BINDING.SIMULATOR_IN_PRODUCTION', 'Simulators cannot serve a production installation without an explicit override.');
        }

        return $binding;
    }

    public function isProduction(): bool
    {
        return $this->installationEnvironment === 'production';
    }
}

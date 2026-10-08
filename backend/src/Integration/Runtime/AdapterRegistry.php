<?php

declare(strict_types=1);

namespace Fundly\Integration\Runtime;

use Fundly\Integration\Runtime\Errors\NonRetryableError;
use LogicException;

/**
 * Every adapter the installation ships (FR-CBA-001/020). Adapters are
 * registered by key; which one serves a tenant is decided by its binding.
 */
final class AdapterRegistry
{
    /** @var array<string, AdapterDefinition> */
    private array $definitions = [];

    public function register(AdapterDefinition $definition): void
    {
        if (isset($this->definitions[$definition->key])) {
            throw new LogicException("Adapter {$definition->key} registered twice.");
        }
        $this->definitions[$definition->key] = $definition;
    }

    public function get(string $key): AdapterDefinition
    {
        return $this->definitions[$key] ?? throw new NonRetryableError('INTEGRATION.ADAPTER.UNKNOWN', "No adapter is registered under key {$key}.");
    }

    /** @return array<string, AdapterDefinition> */
    public function all(): array
    {
        return $this->definitions;
    }
}

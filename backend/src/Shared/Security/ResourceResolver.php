<?php

declare(strict_types=1);

namespace Fundly\Shared\Security;

use Closure;
use Fundly\Shared\Exceptions\NotFound;

/**
 * Turns a ResourceRef into the attributes used for scope checks. Modules
 * register a loader per entity type in their service providers.
 */
final class ResourceResolver
{
    /** @var array<string, Closure(string): ?ResourceAttributes> */
    private array $loaders = [];

    /** @param Closure(string): ?ResourceAttributes $loader */
    public function register(string $type, Closure $loader): void
    {
        $this->loaders[$type] = $loader;
    }

    public function resolve(ResourceRef $ref): ResourceAttributes
    {
        $loader = $this->loaders[$ref->type] ?? null;
        if ($loader === null) {
            return new ResourceAttributes(entityType: $ref->type, entityId: $ref->id);
        }
        $attributes = $loader($ref->id);
        if ($attributes === null) {
            throw new NotFound(sprintf('%s %s was not found.', $ref->type, $ref->id));
        }

        return $attributes;
    }
}

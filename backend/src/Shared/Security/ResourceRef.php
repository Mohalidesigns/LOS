<?php

declare(strict_types=1);

namespace Fundly\Shared\Security;

/** Reference to an entity a command acts on; resolved to ResourceAttributes for scope checks. */
final readonly class ResourceRef
{
    public function __construct(public string $type, public string $id) {}
}

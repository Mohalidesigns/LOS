<?php

declare(strict_types=1);

namespace Fundly\Integration\Runtime;

use Closure;
use Fundly\Integration\Runtime\Models\AdapterBinding;

final readonly class AdapterDefinition
{
    /** @param Closure(AdapterBinding): object $factory */
    public function __construct(
        public string $key,
        public string $port,
        public string $version,
        public CapabilityManifest $manifest,
        public Closure $factory,
        public bool $isSimulator = false,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace Fundly\Modules\Platform\Contracts;

use Closure;

/**
 * Lets other modules declare configuration artefact types (products, rule
 * sets, workflows, matrices …) and their content validators without
 * depending on Platform internals (FR-CFG-002).
 */
interface ConfigTypeCatalogue
{
    /** @param array<string, mixed>|Closure(array<string, mixed>): void $rules Laravel rules, or a closure that throws ValidationFailed */
    public function register(string $type, string $description, array|Closure $rules): void;
}

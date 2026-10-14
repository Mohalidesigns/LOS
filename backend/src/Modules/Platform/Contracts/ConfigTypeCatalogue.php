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

    /**
     * A check run when a version is put live (request and execution), on top of
     * the content validator: e.g. a jurisdiction pack must be fully verified.
     *
     * @param  Closure(array<string, mixed>): void  $gate  throws ValidationFailed / DomainRuleViolation to refuse
     */
    public function registerActivationGate(string $type, Closure $gate): void;
}

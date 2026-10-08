<?php

declare(strict_types=1);

namespace Fundly\Modules\Application\Contracts;

/**
 * Lets other modules add preconditions to staff actions without the
 * Application module knowing about them (dependency inversion): e.g. Credit
 * blocks "recommend" until a current decision and memo exist. Implementations
 * are tagged with TAG in the module's service provider.
 */
interface TransitionGuard
{
    public const TAG = 'application.transition_guards';

    /** @return list<string> human-readable blockers; empty means allowed */
    public function blockers(ApplicationSummary $application, string $action): array;
}

<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Http\Middleware;

use Closure;
use Fundly\Shared\Clock\Clock;
use Fundly\Shared\Security\CurrentPrincipal;
use Fundly\Shared\Security\StepUpRequired;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `stepup:<minutes>`: the staff session must have re-authenticated within the
 * window (FR-SEC-013). Token principals cannot step up, so they are refused.
 */
final class RequireStepUp
{
    public function __construct(private readonly CurrentPrincipal $principal, private readonly Clock $clock)
    {
    }

    public function handle(Request $request, Closure $next, ?string $minutes = null): Response
    {
        $window = $minutes !== null && ctype_digit($minutes) ? (int) $minutes : (int) config('fundly.auth.step_up_default_minutes', 5);
        $principal = $this->principal->require();
        if ($principal->viaToken() || ! $principal->steppedUpWithin($window, $this->clock->now())) {
            throw new StepUpRequired($window);
        }

        return $next($request);
    }
}

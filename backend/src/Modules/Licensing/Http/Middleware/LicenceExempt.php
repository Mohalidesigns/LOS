<?php

declare(strict_types=1);

namespace Fundly\Modules\Licensing\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Marker read by EnforceLicence: this route stays available whatever the licence state (D-034). */
final class LicenceExempt
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }
}

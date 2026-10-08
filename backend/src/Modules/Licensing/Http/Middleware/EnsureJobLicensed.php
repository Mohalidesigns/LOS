<?php

declare(strict_types=1);

namespace Fundly\Modules\Licensing\Http\Middleware;

use Closure;
use Fundly\Modules\Licensing\Application\LicenceGuard;
use Fundly\Modules\Licensing\Contracts\LicenceFailSafe;

/**
 * Queue job middleware: ordinary jobs need an operational licence; jobs marked
 * LicenceFailSafe (sagas, outbox, reconciliation) always run (D-034).
 */
final class EnsureJobLicensed
{
    public function __construct(private readonly LicenceGuard $guard) {}

    public function handle(object $job, Closure $next): mixed
    {
        if (! $job instanceof LicenceFailSafe) {
            $this->guard->assertOperational();
        }

        return $next($job);
    }
}

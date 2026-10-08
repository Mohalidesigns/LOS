<?php

declare(strict_types=1);

namespace Fundly\Shared\Clock;

use DateTimeImmutable;
use Illuminate\Support\Carbon;

/**
 * Wall clock in UTC. Backed by Carbon so tests that travel in time move the
 * domain clock and Eloquent timestamps together. (Infrastructure: the domain
 * depends only on the Clock interface.)
 */
final class SystemClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return Carbon::now('UTC')->toDateTimeImmutable();
    }
}

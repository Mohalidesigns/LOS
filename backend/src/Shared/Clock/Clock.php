<?php

declare(strict_types=1);

namespace Fundly\Shared\Clock;

use DateTimeImmutable;

/**
 * Source of "now". Always UTC; rendering in the tenant timezone is a
 * presentation concern (TRD §4.1).
 */
interface Clock
{
    public function now(): DateTimeImmutable;
}

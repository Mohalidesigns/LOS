<?php

declare(strict_types=1);

namespace Fundly\Integration\Runtime\Resilience;

interface RandomSource
{
    /** Uniform integer in [min, max]. */
    public function between(int $min, int $max): int;
}

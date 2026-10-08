<?php

declare(strict_types=1);

namespace Fundly\Integration\Runtime\Resilience;

final class SecureRandom implements RandomSource
{
    public function between(int $min, int $max): int
    {
        return random_int($min, $max);
    }
}

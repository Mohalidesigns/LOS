<?php

declare(strict_types=1);

namespace Fundly\Integration\Runtime\Resilience;

/** Deterministic xorshift64* source: reproducible jitter and fault injection in tests. */
final class SeededRandom implements RandomSource
{
    private int $state;

    public function __construct(int $seed)
    {
        $this->state = $seed === 0 ? 0x9E3779B9 : $seed;
    }

    public function between(int $min, int $max): int
    {
        $x = $this->state;
        $x ^= ($x << 13) & PHP_INT_MAX;
        $x ^= ($x >> 7);
        $x ^= ($x << 17) & PHP_INT_MAX;
        $this->state = $x & PHP_INT_MAX;
        $span = $max - $min + 1;

        return $min + ($this->state % $span);
    }
}

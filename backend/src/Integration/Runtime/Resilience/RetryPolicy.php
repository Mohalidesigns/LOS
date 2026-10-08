<?php

declare(strict_types=1);

namespace Fundly\Integration\Runtime\Resilience;

use InvalidArgumentException;

/**
 * Exponential backoff with *full jitter* (TRD §11):
 *   delay(attempt) = random_between(0, min(cap, base · 2^(attempt−1)))
 * Integer milliseconds only.
 */
final readonly class RetryPolicy
{
    public function __construct(
        public int $maxAttempts,
        public int $baseDelayMs,
        public int $maxDelayMs,
        private RandomSource $random,
    ) {
        if ($maxAttempts < 1 || $baseDelayMs < 1 || $maxDelayMs < $baseDelayMs) {
            throw new InvalidArgumentException('Invalid retry policy.');
        }
    }

    /** Upper bound of the jitter window for the given (1-based) attempt that just failed. */
    public function ceilingMs(int $attempt): int
    {
        $exp = min(max($attempt - 1, 0), 30);

        return (int) min($this->maxDelayMs, $this->baseDelayMs * (2 ** $exp));
    }

    public function delayMs(int $attempt): int
    {
        return $this->random->between(0, $this->ceilingMs($attempt));
    }

    public function hasAttemptsLeft(int $attemptsMade): bool
    {
        return $attemptsMade < $this->maxAttempts;
    }
}

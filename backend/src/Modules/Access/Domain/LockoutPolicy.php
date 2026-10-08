<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Domain;

use DateInterval;
use DateTimeImmutable;

/**
 * Account lockout with exponential backoff (TRD §8.2): after `threshold`
 * consecutive failures the account locks for base·2^(failures−threshold)
 * minutes, capped at `max` minutes.
 */
final readonly class LockoutPolicy
{
    public function __construct(public int $threshold, public int $baseMinutes, public int $maxMinutes) {}

    public function lockUntil(int $consecutiveFailures, DateTimeImmutable $now): ?DateTimeImmutable
    {
        if ($consecutiveFailures < $this->threshold) {
            return null;
        }
        $exponent = min($consecutiveFailures - $this->threshold, 16);
        $minutes = min($this->baseMinutes * (2 ** $exponent), $this->maxMinutes);

        return $now->add(new DateInterval('PT'.$minutes.'M'));
    }
}

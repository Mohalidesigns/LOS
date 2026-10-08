<?php

declare(strict_types=1);

namespace Fundly\Shared\Clock;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;

/** Deterministic clock for tests and replay. */
final class FrozenClock implements Clock
{
    private DateTimeImmutable $now;

    public function __construct(?DateTimeImmutable $now = null)
    {
        $this->now = ($now ?? new DateTimeImmutable('now'))->setTimezone(new DateTimeZone('UTC'));
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function set(DateTimeImmutable $now): void
    {
        $this->now = $now->setTimezone(new DateTimeZone('UTC'));
    }

    public function advance(string $isoDuration): void
    {
        $this->now = $this->now->add(new DateInterval($isoDuration));
    }
}

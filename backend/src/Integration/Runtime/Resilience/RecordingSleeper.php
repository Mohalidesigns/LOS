<?php

declare(strict_types=1);

namespace Fundly\Integration\Runtime\Resilience;

/** Records requested delays instead of sleeping (tests, simulators). */
final class RecordingSleeper implements Sleeper
{
    /** @var list<int> */
    public array $sleeps = [];

    public function sleepMs(int $milliseconds): void
    {
        $this->sleeps[] = $milliseconds;
    }
}

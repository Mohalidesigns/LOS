<?php

declare(strict_types=1);

namespace Fundly\Integration\Runtime\Resilience;

final class RealSleeper implements Sleeper
{
    public function sleepMs(int $milliseconds): void
    {
        if ($milliseconds > 0) {
            usleep($milliseconds * 1000);
        }
    }
}

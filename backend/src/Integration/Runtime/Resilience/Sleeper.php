<?php

declare(strict_types=1);

namespace Fundly\Integration\Runtime\Resilience;

interface Sleeper
{
    public function sleepMs(int $milliseconds): void;
}

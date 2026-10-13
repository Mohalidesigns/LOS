<?php

declare(strict_types=1);

namespace Fundly\Modules\Compliance\Contracts;

/** Result of a screening run plus the alerts still standing on the application. */
final readonly class ScreeningOutcome
{
    public function __construct(public int $runs, public int $newHits, public int $openAlerts) {}

    public function isClear(): bool
    {
        return $this->openAlerts === 0;
    }
}

<?php

declare(strict_types=1);

namespace Fundly\Integration\Ports\CoreBanking;

use Fundly\Integration\Ports\CoreBanking\Dto\Instalment;
use Fundly\Integration\Ports\CoreBanking\Dto\ScheduleRequest;

/**
 * Schedule sub-port.
 */
interface SchedulePort
{
    /**
     * @return list<Instalment>
     */
    public function generateSchedule(ScheduleRequest $request): array;

    /**
     * @return list<Instalment>
     */
    public function getSchedule(string $loanAccountNo): array;
}

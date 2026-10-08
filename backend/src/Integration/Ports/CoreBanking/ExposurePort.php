<?php

declare(strict_types=1);

namespace Fundly\Integration\Ports\CoreBanking;

use Fundly\Integration\Ports\CoreBanking\Dto\ExposureFacility;

/**
 * Exposure sub-port.
 */
interface ExposurePort
{
    /**
     * @param  list<string>  $connectedCustomerIds
     * @return list<ExposureFacility>
     */
    public function getExposure(string $cbaCustomerId, array $connectedCustomerIds = []): array;
}

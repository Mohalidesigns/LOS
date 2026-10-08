<?php

declare(strict_types=1);

namespace Fundly\Integration\Ports\CoreBanking;

use Fundly\Integration\Ports\CoreBanking\Dto\ReferenceItem;

/**
 * Reference data sub-port (cached by the runtime, TTL per type).
 */
interface ReferenceDataPort
{
    /**
     * @return list<ReferenceItem>
     */
    public function getProducts(): array;

    /**
     * @return list<ReferenceItem>
     */
    public function getBranches(): array;

    /**
     * @return list<ReferenceItem>
     */
    public function getGlCodes(): array;

    /**
     * @return list<ReferenceItem>
     */
    public function getCurrencies(): array;

    /**
     * @return array<string, string> currency => rate to base, decimal strings
     */
    public function getRates(): array;
}

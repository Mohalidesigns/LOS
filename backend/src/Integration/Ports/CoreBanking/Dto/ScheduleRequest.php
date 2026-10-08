<?php

declare(strict_types=1);

namespace Fundly\Integration\Ports\CoreBanking\Dto;

use Fundly\Shared\Money\Money;

/**
 * Canonical DTO, CBI v1.0 (integration register §2.1).
 */
final readonly class ScheduleRequest
{
    public function __construct(
        public string $productCode,
        public Money $principal,
        public string $ratePercent,
        public int $tenorMonths,
        public string $frequency,
        public int $moratoriumMonths,
        public string $startDate,
        public string $dayCount,
    ) {
    }
}

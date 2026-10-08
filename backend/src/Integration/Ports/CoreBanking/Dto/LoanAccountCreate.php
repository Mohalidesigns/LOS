<?php

declare(strict_types=1);

namespace Fundly\Integration\Ports\CoreBanking\Dto;

use Fundly\Shared\Money\Money;

/**
 * Canonical DTO, CBI v1.0 (integration register §2.1).
 *
 * ratePercent is a decimal string (e.g. "24.5"), never a float.
 */
final readonly class LoanAccountCreate
{
    /**
     * @param  array<string, string>  $glMapping
     */
    public function __construct(
        public string $losFacilityId,
        public string $cbaCustomerId,
        public string $productCode,
        public Money $principal,
        public string $ratePercent,
        public int $tenorMonths,
        public string $frequency,
        public int $moratoriumMonths,
        public string $startDate,
        public string $dayCount,
        public string $branchCode,
        public array $glMapping = [],
    ) {
    }
}

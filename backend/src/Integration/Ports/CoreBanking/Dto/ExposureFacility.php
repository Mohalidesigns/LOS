<?php

declare(strict_types=1);

namespace Fundly\Integration\Ports\CoreBanking\Dto;

use Fundly\Shared\Money\Money;

/**
 * Canonical DTO, CBI v1.0 (integration register §2.1).
 *
 * Existing facility as seen by the CBA (FR-CRD-007 input).
 */
final readonly class ExposureFacility
{
    public function __construct(
        public string $reference,
        public string $productCode,
        public Money $limit,
        public Money $outstanding,
        public Money $arrears,
        public string $classification,
        public int $daysPastDue,
    ) {}
}

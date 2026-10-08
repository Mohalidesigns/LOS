<?php

declare(strict_types=1);

namespace Fundly\Integration\Ports\CoreBanking\Dto;

use Fundly\Shared\Money\Money;

/**
 * Canonical DTO, CBI v1.0 (integration register §2.1).
 */
final readonly class LoanAccount
{
    public function __construct(
        public string $loanAccountNo,
        public string $cbaCustomerId,
        public string $productCode,
        public Money $principal,
        public Money $outstanding,
        public string $status,
        public ?string $losFacilityId = null,
    ) {}
}

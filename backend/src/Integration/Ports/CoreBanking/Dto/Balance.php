<?php

declare(strict_types=1);

namespace Fundly\Integration\Ports\CoreBanking\Dto;

use Fundly\Shared\Money\Money;

/**
 * Canonical DTO, CBI v1.0 (integration register §2.1).
 */
final readonly class Balance
{
    public function __construct(
        public Money $ledger,
        public Money $available,
        public string $asOf,
    ) {
    }
}

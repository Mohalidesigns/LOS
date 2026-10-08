<?php

declare(strict_types=1);

namespace Fundly\Integration\Ports\CoreBanking\Dto;

use Fundly\Shared\Money\Money;

/**
 * Canonical DTO, CBI v1.0 (integration register §2.1).
 */
final readonly class StatementLine
{
    public function __construct(
        public string $date,
        public string $reference,
        public Money $amount,
        public string $direction,
    ) {}
}

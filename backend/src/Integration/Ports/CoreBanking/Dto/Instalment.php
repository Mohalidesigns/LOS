<?php

declare(strict_types=1);

namespace Fundly\Integration\Ports\CoreBanking\Dto;

use Fundly\Shared\Money\Money;

/**
 * Canonical DTO, CBI v1.0 (integration register §2.1).
 */
final readonly class Instalment
{
    public function __construct(
        public int $number,
        public string $dueDate,
        public Money $principal,
        public Money $interest,
        public Money $total,
        public Money $closingBalance,
    ) {}
}

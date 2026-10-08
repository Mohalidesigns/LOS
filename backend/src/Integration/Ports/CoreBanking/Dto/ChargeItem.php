<?php

declare(strict_types=1);

namespace Fundly\Integration\Ports\CoreBanking\Dto;

use Fundly\Shared\Money\Money;

/**
 * Canonical DTO, CBI v1.0 (integration register §2.1).
 */
final readonly class ChargeItem
{
    public function __construct(
        public string $code,
        public Money $amount,
        public ?string $taxCode = null,
    ) {}
}

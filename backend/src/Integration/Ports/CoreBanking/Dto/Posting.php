<?php

declare(strict_types=1);

namespace Fundly\Integration\Ports\CoreBanking\Dto;

use Fundly\Shared\Money\Money;

/**
 * Canonical DTO, CBI v1.0 (integration register §2.1).
 */
final readonly class Posting
{
    public function __construct(
        public string $postingRef,
        public string $reference,
        public string $accountNo,
        public Money $amount,
        public string $status,
        public string $valueDate,
    ) {}
}

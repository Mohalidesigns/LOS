<?php

declare(strict_types=1);

namespace Fundly\Integration\Ports\CoreBanking\Dto;

use Fundly\Shared\Money\Money;

/**
 * Canonical DTO, CBI v1.0 (integration register §2.1).
 *
 * losReference is the idempotency key, also written to a CBA-visible reference field (register §2.3).
 */
final readonly class DisbursementRequest
{
    public function __construct(
        public string $loanAccountNo,
        public Money $amount,
        public Destination $destination,
        public string $narration,
        public string $valueDate,
        public string $losReference,
    ) {
    }
}

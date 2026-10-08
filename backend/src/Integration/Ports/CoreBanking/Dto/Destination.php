<?php

declare(strict_types=1);

namespace Fundly\Integration\Ports\CoreBanking\Dto;

/**
 * Canonical DTO, CBI v1.0 (integration register §2.1).
 *
 * Where disbursed funds go: an internal account or an NIP beneficiary.
 */
final readonly class Destination
{
    public function __construct(
        public string $type,
        public string $accountNo,
        public ?string $bankCode = null,
        public ?string $accountName = null,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace Fundly\Integration\Ports\CoreBanking\Dto;

/**
 * Canonical DTO, CBI v1.0 (integration register §2.1).
 *
 * Search keys for searchCustomers; at least one must be set.
 */
final readonly class CustomerSearchCriteria
{
    public function __construct(
        public ?string $bvn = null,
        public ?string $nin = null,
        public ?string $rcNumber = null,
        public ?string $accountNo = null,
        public ?string $phone = null,
        public ?string $name = null,
    ) {
    }
}

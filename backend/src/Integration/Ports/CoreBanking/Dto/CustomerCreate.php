<?php

declare(strict_types=1);

namespace Fundly\Integration\Ports\CoreBanking\Dto;

/**
 * Canonical DTO, CBI v1.0 (integration register §2.1).
 */
final readonly class CustomerCreate
{
    public function __construct(
        public string $losPartyId,
        public string $kind,
        public string $name,
        public ?string $bvn = null,
        public ?string $nin = null,
        public ?string $rcNumber = null,
        public ?string $dateOfBirth = null,
        public ?string $phone = null,
        public ?string $email = null,
        public ?string $branchCode = null,
        public ?string $segment = null,
    ) {}
}

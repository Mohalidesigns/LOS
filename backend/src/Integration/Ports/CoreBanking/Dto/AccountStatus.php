<?php

declare(strict_types=1);

namespace Fundly\Integration\Ports\CoreBanking\Dto;

/**
 * Canonical DTO, CBI v1.0 (integration register §2.1).
 *
 * status: active | dormant | closed | frozen
 */
final readonly class AccountStatus
{
    public function __construct(
        public string $status,
        public string $ownerName,
    ) {}
}

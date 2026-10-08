<?php

declare(strict_types=1);

namespace Fundly\Integration\Ports\CoreBanking\Dto;

/**
 * Canonical DTO, CBI v1.0 (integration register §2.1).
 *
 * matchScore: 0-100 when the provider returns one.
 */
final readonly class NameEnquiryResult
{
    public function __construct(
        public string $accountName,
        public ?int $matchScore = null,
    ) {}
}

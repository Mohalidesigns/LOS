<?php

declare(strict_types=1);

namespace Fundly\Integration\Ports\CoreBanking\Dto;

/**
 * Canonical DTO, CBI v1.0 (integration register §2.1).
 *
 * status: posted | pending | rejected
 */
final readonly class PostingResult
{
    public function __construct(
        public string $postingRef,
        public string $status,
    ) {
    }
}

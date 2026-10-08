<?php

declare(strict_types=1);

namespace Fundly\Integration\Ports\CoreBanking\Dto;

/**
 * Canonical DTO, CBI v1.0 (integration register §2.1).
 *
 * Generic reference-data row (product, branch, GL code, currency).
 */
final readonly class ReferenceItem
{
    /**
     * @param  array<string, string>  $attributes
     */
    public function __construct(
        public string $code,
        public string $name,
        public array $attributes = [],
    ) {
    }
}

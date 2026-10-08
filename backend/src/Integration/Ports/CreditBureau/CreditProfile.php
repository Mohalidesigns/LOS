<?php

declare(strict_types=1);

namespace Fundly\Integration\Ports\CreditBureau;

/**
 * Canonical credit profile (FR-CRD-004): every bureau adapter maps its
 * report into this shape. Money as decimal strings in NGN.
 */
final readonly class CreditProfile
{
    /** @param list<array{lender: string, type: string, outstanding: string, monthly_instalment: string, dpd: int, status: string}> $facilities */
    public function __construct(
        public string $bureau,
        public string $reportReference,
        public bool $hit,
        public ?int $score,
        public array $facilities,
        public int $enquiries6m,
        public bool $hasWriteOff,
    ) {}
}

<?php

declare(strict_types=1);

namespace Fundly\Modules\Collateral\Contracts;

use DateTimeImmutable;

/** A valuation or insurance policy about to lapse (or lapsed) on live collateral. */
final readonly class CollateralAttention
{
    /** @param 'revaluation'|'insurance_expiry' $kind */
    public function __construct(
        public string $kind,
        public string $collateralId,
        public string $applicationId,
        public string $description,
        public DateTimeImmutable $dueDate,
        public string $sourceId,
    ) {}
}

<?php

declare(strict_types=1);

namespace Fundly\Modules\Collateral\Contracts;

use Fundly\Shared\Money\Money;

final readonly class CollateralCoverage
{
    public function __construct(
        public Money $exposure,
        public Money $lendingValue,
        public Money $marketValue,
        public ?string $coverPercent,
        public ?string $ltvPercent,
        public ?string $minCoverPercent,
        public ?string $maxLtvPercent,
        public ?bool $meetsCover,
        public ?bool $meetsLtv,
        public int $itemCount,
    ) {}
}

<?php

declare(strict_types=1);

namespace Fundly\Modules\Collateral\Contracts;

/** Whether the security behind an application allows disbursement (FR-COL-008). */
final readonly class SecurityPosition
{
    /**
     * @param  list<string>  $perfectionBlockers  precedent steps outstanding (waived steps do not block)
     * @param  list<string>  $insuranceBlockers  items needing insurance without an in-force policy noting the bank's interest
     */
    public function __construct(
        public bool $applicable,
        public bool $insuranceApplicable,
        public array $perfectionBlockers,
        public array $insuranceBlockers,
        public int $openSubsequentSteps,
    ) {}

    public function isClear(): bool
    {
        return $this->perfectionBlockers === [] && $this->insuranceBlockers === [];
    }
}

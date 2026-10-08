<?php

declare(strict_types=1);

namespace Fundly\Modules\Credit\Contracts;

use Fundly\Shared\Money\Money;

/** What approvals and offers need from the credit file. */
final readonly class CreditDecisionView
{
    public function __construct(
        public string $decisionId,
        public string $outcome,
        public string $riskGrade,
        public Money $recommendedAmount,
        public int $tenorMonths,
        public string $ratePercent,
        public int $exceptionCount,
        public ?string $maxExceptionSeverity,
        public ?string $memoRecommendation,
    ) {}
}

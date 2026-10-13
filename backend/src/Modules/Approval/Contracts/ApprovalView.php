<?php

declare(strict_types=1);

namespace Fundly\Modules\Approval\Contracts;

use DateTimeImmutable;
use Fundly\Shared\Money\Money;

/** The standing approval of an application, as offers, CPs and disbursement need it. */
final readonly class ApprovalView
{
    /**
     * @param  list<ApprovalConditionView>  $conditions
     * @param  list<string>  $voterIds  everyone who voted on this request (for checker ≠ approver, FR-DSB-009)
     */
    public function __construct(
        public string $requestId,
        public string $status,
        public Money $exposure,
        public ?DateTimeImmutable $decidedAt,
        public ?DateTimeImmutable $validUntil,
        public ?Money $counterAmount,
        public ?int $counterTenorMonths,
        public array $conditions,
        public array $voterIds = [],
    ) {}

    /** Approved and still within validity: disbursement re-checks this at release (FR-APV-012). */
    public function isValidAt(DateTimeImmutable $at): bool
    {
        return $this->status === 'approved' && $this->validUntil !== null && $at < $this->validUntil;
    }
}

<?php

declare(strict_types=1);

namespace Fundly\Modules\Disbursement\Contracts;

use DateTimeImmutable;
use Fundly\Shared\Money\Money;

/** A booked facility as the CBA holds it, for handover (FR-HND-001/003). */
final readonly class BookedFacility
{
    /**
     * @param  list<array{code: string, name: string, amount: string}>  $fees
     * @param  list<array<string, mixed>>  $schedule  as booked in the CBA
     */
    public function __construct(
        public string $disbursementId,
        public string $loanAccountNo,
        public string $cbaCustomerId,
        public string $postingRef,
        public Money $principal,
        public Money $net,
        public array $fees,
        public string $valueDate,
        public DateTimeImmutable $bookedAt,
        public array $schedule,
        public ?string $adviceDocumentId,
    ) {}
}

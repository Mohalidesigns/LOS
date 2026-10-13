<?php

declare(strict_types=1);

namespace Fundly\Modules\Offer\Contracts;

use DateTimeImmutable;
use Fundly\Shared\Money\Money;

/**
 * The executed offer, as booking needs it: the accepted terms and schedule
 * (compared with the CBA schedule at booking, LOS-FR-313) and the agreement evidence.
 */
final readonly class AcceptedOffer
{
    /**
     * @param  list<array{number: int, due_date: string, principal: string, interest: string, total: string, closing_balance: string}>  $schedule
     * @param  list<array{code: string, name: string, type: string, amount: string, costed: bool}>  $fees  as disclosed in the key facts
     */
    public function __construct(
        public string $offerId,
        public string $agreementId,
        public Money $principal,
        public int $tenorMonths,
        public string $ratePercent,
        public string $repaymentFrequency,
        public array $schedule,
        public string $agreementSha256,
        public string $templateVersionId,
        public DateTimeImmutable $executedAt,
        public array $fees = [],
        public ?Money $netDisbursed = null,
    ) {}
}

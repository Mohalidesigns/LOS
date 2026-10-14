<?php

declare(strict_types=1);

namespace Fundly\Modules\Collateral\Contracts;

use Fundly\Shared\Money\Money;

final readonly class SecuredItem
{
    /**
     * @param  list<array{step_id: string, code: string, name: string, timing: string, status: string, responsible_id: ?string, due_date: ?string}>  $openSteps
     * @param  array{policy_no: string, insurer: string, sum_insured: array{amount: string, currency: string}, expiry_date: string, bank_interest_noted: bool}|null  $insurance
     */
    public function __construct(
        public string $collateralId,
        public string $typeCode,
        public string $category,
        public string $description,
        public ?string $ownerPartyId,
        public string $ownerName,
        public Money $allocated,
        public Money $marketValue,
        public Money $forcedSaleValue,
        public ?string $valuationValidUntil,
        public array $openSteps,
        public ?array $insurance,
    ) {}
}

<?php

declare(strict_types=1);

namespace Fundly\Modules\Collateral\Application\Commands;

use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Bus\ValidatesInput;
use Fundly\Shared\Security\ResourceRef;

/** Record an insurance policy on an item: insurer, sum insured, expiry and whether the bank's interest is noted (FR-COL-006). */
#[HandledBy(RecordInsuranceHandler::class)]
final readonly class RecordInsurance implements Command, ValidatesInput
{
    public function __construct(public string $collateralId, public ?string $policyNo, public ?string $insurer, public ?string $sumInsured, public ?string $startDate, public ?string $expiryDate, public bool $bankInterestNoted, public ?string $tempPath = null, public ?string $originalName = null) {}

    public function action(): string
    {
        return 'collateral.insured';
    }

    public function permission(): string
    {
        return 'collateral:manage';
    }

    public function resource(): ResourceRef
    {
        return new ResourceRef('collateral', $this->collateralId);
    }

    public function data(): array
    {
        return ['policy_no' => $this->policyNo, 'insurer' => $this->insurer, 'sum_insured' => $this->sumInsured, 'start_date' => $this->startDate, 'expiry_date' => $this->expiryDate, 'bank_interest_noted' => $this->bankInterestNoted];
    }

    public function rules(): array
    {
        return ['policy_no' => ['required', 'string', 'max:64'], 'insurer' => ['required', 'string', 'min:2', 'max:200'], 'sum_insured' => ['required', 'string', 'regex:/^\\d{1,16}(\\.\\d{1,4})?$/'], 'start_date' => ['required', 'date_format:Y-m-d'], 'expiry_date' => ['required', 'date_format:Y-m-d', 'after:start_date']];
    }
}

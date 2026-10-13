<?php

declare(strict_types=1);

namespace Fundly\Modules\Conditions\Application\Commands;

use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Bus\ValidatesInput;
use Fundly\Shared\Security\ResourceRef;

/** The final pre-disbursement checklist, with a fresh re-screen and a destination name enquiry (FR-CPR-003..005). */
#[HandledBy(RunPreDisbursementCheckHandler::class)]
final readonly class RunPreDisbursementCheck implements Command, ValidatesInput
{
    public function __construct(public string $applicationId, public string $bankCode, public string $accountNo) {}

    public function action(): string
    {
        return 'conditions.pre_disbursement_checked';
    }

    public function permission(): string
    {
        return 'condition:manage';
    }

    public function resource(): ResourceRef
    {
        return new ResourceRef('application', $this->applicationId);
    }

    public function data(): array
    {
        return ['bank_code' => $this->bankCode, 'account_no' => $this->accountNo];
    }

    public function rules(): array
    {
        return ['bank_code' => ['required', 'regex:/^\\d{3,6}$/'], 'account_no' => ['required', 'regex:/^\\d{10}$/']];
    }
}

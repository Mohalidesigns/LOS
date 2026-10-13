<?php

declare(strict_types=1);

namespace Fundly\Modules\Disbursement\Application\Commands;

use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Bus\ValidatesInput;
use Fundly\Shared\Security\ResourceRef;

/** Compare LOS disbursement postings with the CBA's for a period (FR-DSB-008, FR-CBA-017). */
#[HandledBy(RunReconciliationHandler::class)]
final readonly class RunReconciliation implements Command, ValidatesInput
{
    public function __construct(public ?string $from, public ?string $to) {}

    public function action(): string
    {
        return 'reconciliation.run';
    }

    public function permission(): string
    {
        return 'reconciliation:run';
    }

    public function resource(): ?ResourceRef
    {
        return null;
    }

    public function data(): array
    {
        return ['from' => $this->from, 'to' => $this->to];
    }

    public function rules(): array
    {
        return ['from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from']];
    }
}

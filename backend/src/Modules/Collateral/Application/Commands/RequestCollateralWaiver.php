<?php

declare(strict_types=1);

namespace Fundly\Modules\Collateral\Application\Commands;

use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Bus\ValidatesInput;
use Fundly\Shared\Security\ResourceRef;

/** Ask the exception authority to waive a perfection step or an item's insurance requirement (FR-COL-008): maker-checker. */
#[HandledBy(RequestCollateralWaiverHandler::class)]
final readonly class RequestCollateralWaiver implements Command, ValidatesInput
{
    public function __construct(public string $target, public string $id, public ?string $reason) {}

    public function action(): string
    {
        return 'collateral.waiver_requested';
    }

    public function permission(): string
    {
        return 'collateral:manage';
    }

    public function resource(): ResourceRef
    {
        return new ResourceRef($this->target === 'insurance' ? 'collateral' : 'perfection_step', $this->id);
    }

    public function data(): array
    {
        return ['target' => $this->target, 'reason' => $this->reason];
    }

    public function rules(): array
    {
        return ['target' => ['required', 'in:perfection_step,insurance'], 'reason' => ['required', 'string', 'min:10', 'max:2000']];
    }
}

<?php

declare(strict_types=1);

namespace Fundly\Modules\Collateral\Application\Commands;

use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Bus\ValidatesInput;
use Fundly\Shared\Security\ResourceRef;

/** Set how much of an item's lending value secures a facility (FR-COL-005); 0 releases it. Over-allocation is refused. */
#[HandledBy(AllocateCollateralHandler::class)]
final readonly class AllocateCollateral implements Command, ValidatesInput
{
    public function __construct(public string $collateralId, public ?string $applicationId, public ?string $amount) {}

    public function action(): string
    {
        return 'collateral.allocated';
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
        return ['application_id' => $this->applicationId, 'amount' => $this->amount];
    }

    public function rules(): array
    {
        return ['application_id' => ['required', 'uuid'], 'amount' => ['required', 'string', 'regex:/^\\d{1,16}(\\.\\d{1,4})?$/']];
    }
}

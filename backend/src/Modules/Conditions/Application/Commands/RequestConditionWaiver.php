<?php

declare(strict_types=1);

namespace Fundly\Modules\Conditions\Application\Commands;

use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Bus\ValidatesInput;
use Fundly\Shared\Security\ResourceRef;

/** Ask the configured authority to waive a condition (FR-CPR-002): maker-checker. */
#[HandledBy(RequestConditionWaiverHandler::class)]
final readonly class RequestConditionWaiver implements Command, ValidatesInput
{
    public function __construct(public string $conditionId, public ?string $reason) {}

    public function action(): string
    {
        return 'condition.waiver_requested';
    }

    public function permission(): string
    {
        return 'condition:manage';
    }

    public function resource(): ResourceRef
    {
        return new ResourceRef('condition', $this->conditionId);
    }

    public function data(): array
    {
        return ['reason' => $this->reason];
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:10', 'max:2000']];
    }
}

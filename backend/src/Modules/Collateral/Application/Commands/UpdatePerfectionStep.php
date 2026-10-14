<?php

declare(strict_types=1);

namespace Fundly\Modules\Collateral\Application\Commands;

use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Bus\ValidatesInput;
use Fundly\Shared\Security\ResourceRef;

/** Assign the responsible party and due date of a perfection step, or mark it in progress (FR-COL-004). */
#[HandledBy(UpdatePerfectionStepHandler::class)]
final readonly class UpdatePerfectionStep implements Command, ValidatesInput
{
    public function __construct(public string $stepId, public ?string $responsibleUserId, public ?string $dueDate, public bool $start) {}

    public function action(): string
    {
        return 'collateral.perfection_updated';
    }

    public function permission(): string
    {
        return 'collateral:manage';
    }

    public function resource(): ResourceRef
    {
        return new ResourceRef('perfection_step', $this->stepId);
    }

    public function data(): array
    {
        return ['responsible_user_id' => $this->responsibleUserId, 'due_date' => $this->dueDate, 'start' => $this->start];
    }

    public function rules(): array
    {
        return ['responsible_user_id' => ['nullable', 'uuid'], 'due_date' => ['nullable', 'date_format:Y-m-d']];
    }
}

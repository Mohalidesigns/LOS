<?php

declare(strict_types=1);

namespace Fundly\Modules\Conditions\Application\Commands;

use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Bus\ValidatesInput;
use Fundly\Shared\Security\ResourceRef;

/** Add a CP or CS with owner and due date (FR-CPR-001). */
#[HandledBy(AddConditionHandler::class)]
final readonly class AddCondition implements Command, ValidatesInput
{
    public function __construct(public string $applicationId, public string $kind, public ?string $description, public bool $mandatory, public ?string $ownerId, public ?string $dueDate) {}

    public function action(): string
    {
        return 'condition.added';
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
        return ['kind' => $this->kind, 'description' => $this->description, 'owner_id' => $this->ownerId, 'due_date' => $this->dueDate];
    }

    public function rules(): array
    {
        return ['kind' => ['required', 'in:precedent,subsequent'], 'description' => ['required', 'string', 'min:5', 'max:1000'], 'owner_id' => ['nullable', 'uuid'], 'due_date' => ['nullable', 'date_format:Y-m-d']];
    }
}

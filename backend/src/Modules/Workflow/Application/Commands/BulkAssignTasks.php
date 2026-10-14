<?php

declare(strict_types=1);

namespace Fundly\Modules\Workflow\Application\Commands;

use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Bus\ValidatesInput;
use Fundly\Shared\Security\ResourceRef;

#[HandledBy(BulkAssignTasksHandler::class)]
final readonly class BulkAssignTasks implements Command, ValidatesInput
{
    public function __construct(/** @var list<string> */ public array $taskIds, public ?string $assigneeId, public ?string $reason) {}

    public function action(): string
    {
        return 'workflow.tasks_bulk_assigned';
    }

    public function permission(): string
    {
        return 'application:view';
    }

    public function resource(): ?ResourceRef
    {
        return null;
    }

    public function data(): array
    {
        return ['task_ids' => $this->taskIds, 'assignee_id' => $this->assigneeId, 'reason' => $this->reason];
    }

    public function rules(): array
    {
        return ['task_ids' => ['required', 'array', 'min:1', 'max:50'], 'task_ids.*' => ['uuid'], 'assignee_id' => ['nullable', 'uuid'], 'reason' => ['nullable', 'string', 'max:500']];
    }
}

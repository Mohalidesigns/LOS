<?php

declare(strict_types=1);

namespace Fundly\Modules\Workflow\Application\Commands;

use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Bus\ValidatesInput;
use Fundly\Shared\Security\ResourceRef;

#[HandledBy(ReassignTaskHandler::class)]
final readonly class ReassignTask implements Command, ValidatesInput
{
    public function __construct(public string $taskId, public ?string $assigneeId, public ?string $reason) {}

    public function action(): string
    {
        return 'workflow.task_reassigned';
    }

    public function permission(): string
    {
        return 'task:reassign';
    }

    public function resource(): ResourceRef
    {
        return new ResourceRef('workflow_task', $this->taskId);
    }

    public function data(): array
    {
        return ['assignee_id' => $this->assigneeId, 'reason' => $this->reason];
    }

    public function rules(): array
    {
        return ['assignee_id' => ['nullable', 'uuid'], 'reason' => ['required', 'string', 'min:5', 'max:500']];
    }
}

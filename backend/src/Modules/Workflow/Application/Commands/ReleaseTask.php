<?php

declare(strict_types=1);

namespace Fundly\Modules\Workflow\Application\Commands;

use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Bus\ValidatesInput;
use Fundly\Shared\Security\ResourceRef;

#[HandledBy(ReleaseTaskHandler::class)]
final readonly class ReleaseTask implements Command, ValidatesInput
{
    public function __construct(public string $taskId, public ?string $reason) {}

    public function action(): string
    {
        return 'workflow.task_released';
    }

    public function permission(): string
    {
        return 'application:view';
    }

    public function resource(): ResourceRef
    {
        return new ResourceRef('workflow_task', $this->taskId);
    }

    public function data(): array
    {
        return ['reason' => $this->reason];
    }

    public function rules(): array
    {
        return ['reason' => ['nullable', 'string', 'max:500']];
    }
}

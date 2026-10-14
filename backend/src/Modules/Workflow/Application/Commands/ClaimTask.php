<?php

declare(strict_types=1);

namespace Fundly\Modules\Workflow\Application\Commands;

use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Bus\ValidatesInput;
use Fundly\Shared\Security\ResourceRef;

#[HandledBy(ClaimTaskHandler::class)]
final readonly class ClaimTask implements Command, ValidatesInput
{
    public function __construct(public string $taskId) {}

    public function action(): string
    {
        return 'workflow.task_claimed';
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
        return [];
    }

    public function rules(): array
    {
        return [];
    }
}

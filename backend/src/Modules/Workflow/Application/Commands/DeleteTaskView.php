<?php

declare(strict_types=1);

namespace Fundly\Modules\Workflow\Application\Commands;

use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Bus\ValidatesInput;
use Fundly\Shared\Security\ResourceRef;

#[HandledBy(DeleteTaskViewHandler::class)]
final readonly class DeleteTaskView implements Command, ValidatesInput
{
    public function __construct(public string $viewId) {}

    public function action(): string
    {
        return 'workflow.view_deleted';
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
        return ['view_id' => $this->viewId];
    }

    public function rules(): array
    {
        return [];
    }
}

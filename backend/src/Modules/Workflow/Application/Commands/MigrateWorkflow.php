<?php

declare(strict_types=1);

namespace Fundly\Modules\Workflow\Application\Commands;

use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Bus\ValidatesInput;
use Fundly\Shared\Security\ResourceRef;

#[HandledBy(MigrateWorkflowHandler::class)]
final readonly class MigrateWorkflow implements Command, ValidatesInput
{
    public function __construct(public string $applicationId, public ?string $reason) {}

    public function action(): string
    {
        return 'workflow.migrated';
    }

    public function permission(): string
    {
        return 'config:activate_request';
    }

    public function resource(): ResourceRef
    {
        return new ResourceRef('application', $this->applicationId);
    }

    public function data(): array
    {
        return ['reason' => $this->reason];
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:10', 'max:1000']];
    }
}

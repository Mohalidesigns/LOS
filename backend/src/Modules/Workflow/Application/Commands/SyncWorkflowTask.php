<?php

declare(strict_types=1);

namespace Fundly\Modules\Workflow\Application\Commands;

use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Security\ResourceRef;

/** System: keep the stage task in step with the canonical status (open, close, pause on hold, resume). */
#[HandledBy(SyncWorkflowTaskHandler::class)]
final readonly class SyncWorkflowTask implements Command
{
    public function __construct(public string $applicationId, public string $from, public string $to, public string $actorId) {}

    public function action(): string
    {
        return 'workflow.task_synced';
    }

    public function permission(): ?string
    {
        return null;
    }

    public function resource(): ResourceRef
    {
        return new ResourceRef('application', $this->applicationId);
    }

    /** @return array<string, mixed> */
    public function data(): array
    {
        return ['from' => $this->from, 'to' => $this->to];
    }
}

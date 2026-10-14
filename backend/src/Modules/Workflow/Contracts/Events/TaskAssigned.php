<?php

declare(strict_types=1);

namespace Fundly\Modules\Workflow\Contracts\Events;

use Fundly\Shared\Bus\DomainEvent;

/** A stage task was routed or reassigned to a user (FR-WFL-004/005). */
final readonly class TaskAssigned implements DomainEvent
{
    public function __construct(public string $tenantId, public string $applicationId, public string $taskId, public string $assignmentId, public string $assigneeId, public string $stageName, public string $byActor) {}

    public function name(): string
    {
        return 'workflow.task_assigned';
    }

    public function payload(): array
    {
        return ['tenant_id' => $this->tenantId, 'application_id' => $this->applicationId, 'task_id' => $this->taskId, 'assignment_id' => $this->assignmentId, 'assignee_id' => $this->assigneeId, 'stage_name' => $this->stageName, 'by' => $this->byActor];
    }
}

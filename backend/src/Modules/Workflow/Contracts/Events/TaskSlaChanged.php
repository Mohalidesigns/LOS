<?php

declare(strict_types=1);

namespace Fundly\Modules\Workflow\Contracts\Events;

use Fundly\Shared\Bus\DomainEvent;

/**
 * A stage task's SLA moved to at_risk (warn the owner) or breached
 * (escalate to the holders of `escalateTo`) (FR-WFL-006).
 */
final readonly class TaskSlaChanged implements DomainEvent
{
    /** @param 'at_risk'|'breached' $state */
    public function __construct(
        public string $tenantId,
        public string $applicationId,
        public string $taskId,
        public string $state,
        public string $stageName,
        public ?string $assigneeId,
        public string $permission,
        public string $escalateTo,
        public string $dueAt,
    ) {}

    public function name(): string
    {
        return 'workflow.task_sla_'.$this->state;
    }

    public function payload(): array
    {
        return ['tenant_id' => $this->tenantId, 'application_id' => $this->applicationId, 'task_id' => $this->taskId, 'state' => $this->state, 'stage_name' => $this->stageName,
            'assignee_id' => $this->assigneeId, 'permission' => $this->permission, 'escalate_to' => $this->escalateTo, 'due_at' => $this->dueAt];
    }
}

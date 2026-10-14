<?php

declare(strict_types=1);

namespace Fundly\Modules\Workflow\Application\Commands;

use Fundly\Modules\Workflow\Application\TaskBook;
use Fundly\Modules\Workflow\Infrastructure\Models\WorkflowTask;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Exceptions\DomainRuleViolation;
use Fundly\Shared\Exceptions\ValidationFailed;
use Fundly\Shared\Security\AuthorizationGate;
use Throwable;

/**
 * Bulk claim (no assignee) or, for supervisors, bulk assignment to one
 * person. All-or-nothing: if any task cannot be taken, none is.
 */
final class BulkAssignTasksHandler implements CommandHandler
{
    public function __construct(private readonly TaskBook $book, private readonly AuthorizationGate $gate) {}

    /** @return array{data: array{assigned: int, task_ids: list<string>}} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof BulkAssignTasks);
        $tasks = WorkflowTask::query()->lockForUpdate()->whereIn('id', $command->taskIds)->get();
        if ($tasks->count() !== count(array_unique($command->taskIds))) {
            throw ValidationFailed::with(['task_ids' => 'Some tasks were not found.']);
        }
        $me = $context->principal->id;
        $assignee = $command->assigneeId ?? $me;
        $supervising = $assignee !== $me;
        if ($supervising && (! is_string($command->reason) || mb_strlen(trim($command->reason)) < 5)) {
            throw ValidationFailed::with(['reason' => 'Give a reason when assigning work to someone else.']);
        }
        $problems = [];
        foreach ($tasks as $t) {
            try {
                if ($supervising) {
                    $this->gate->authorize($context->principal, 'task:reassign', $t->resource());
                    ReassignTaskHandler::reassign($t, $assignee, (string) $command->reason, $this->book, $context);
                } else {
                    if ($t->status !== 'open' || $t->assignee_id !== null) {
                        throw new DomainRuleViolation('not open in a queue');
                    }
                    $decision = $this->gate->authorize($context->principal, $t->permission, $this->book->resource($t));
                    $this->book->assign($t, $me, 'claimed', $me, $decision->onBehalfOf, null, $context);
                }
            } catch (Throwable $e) {
                $problems["task_ids.{$t->application_reference}"] = "{$t->application_reference} ({$t->stage_name}): ".$e->getMessage();
            }
        }
        if ($problems !== []) {
            throw ValidationFailed::with($problems);
        }
        $context->audit(new AuditEntry(action: $command->action(), entityType: 'workflow_task', entityId: $tasks->first()?->id, after: ['task_ids' => array_values($tasks->pluck('id')->all()), 'assignee_id' => $assignee], reasonText: $command->reason));

        return ['data' => ['assigned' => $tasks->count(), 'task_ids' => array_values($tasks->pluck('id')->all())]];
    }
}

<?php

declare(strict_types=1);

namespace Fundly\Modules\Workflow\Application\Commands;

use Fundly\Modules\Workflow\Application\TaskBook;
use Fundly\Modules\Workflow\Application\TaskQueries;
use Fundly\Modules\Workflow\Application\WorkflowFile;
use Fundly\Modules\Workflow\Infrastructure\Models\WorkflowTask;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Exceptions\DomainRuleViolation;
use Fundly\Shared\Exceptions\ValidationFailed;

/** FR-WFL-005: a supervisor (task:reassign) moves work, with the reason and who acted kept in the history. */
final class ReassignTaskHandler implements CommandHandler
{
    public function __construct(private readonly WorkflowFile $file, private readonly TaskBook $book, private readonly TaskQueries $queries) {}

    /** @return array{data: array<string, mixed>} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof ReassignTask);
        $t = WorkflowTask::query()->lockForUpdate()->findOrFail($this->file->task($command->taskId)->id);
        self::reassign($t, $command->assigneeId, (string) $command->reason, $this->book, $context);
        $context->audit(new AuditEntry(action: $command->action(), entityType: 'application', entityId: $t->application_id, after: ['task_id' => $t->id, 'stage' => $t->stage_code, 'assignee_id' => $t->assignee_id], reasonText: $command->reason));

        return ['data' => $this->queries->present($t)];
    }

    public static function reassign(WorkflowTask $t, ?string $assigneeId, string $reason, TaskBook $book, CommandContext $context): void
    {
        if ($t->status !== 'open') {
            throw new DomainRuleViolation("This task is {$t->status}.");
        }
        if ($assigneeId !== null && ! $book->canWork($assigneeId, $t)) {
            throw ValidationFailed::with(['assignee_id' => "That person cannot work this stage: they need {$t->permission} for this application's branch and product."]);
        }
        if ($assigneeId === $t->assignee_id) {
            throw ValidationFailed::with(['assignee_id' => 'The task is already with that person.']);
        }
        $book->assign($t, $assigneeId, 'reassigned', $context->principal->id, null, $reason, $context);
    }
}

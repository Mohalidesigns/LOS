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
use Fundly\Shared\Security\AuthorizationGate;

final class ClaimTaskHandler implements CommandHandler
{
    public function __construct(private readonly WorkflowFile $file, private readonly TaskBook $book, private readonly AuthorizationGate $gate, private readonly TaskQueries $queries) {}

    /** @return array{data: array<string, mixed>} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof ClaimTask);
        $t = WorkflowTask::query()->lockForUpdate()->findOrFail($this->file->task($command->taskId)->id);
        if ($t->status !== 'open') {
            throw new DomainRuleViolation("This task is {$t->status}.");
        }
        if ($t->assignee_id !== null) {
            throw new DomainRuleViolation($t->assignee_id === $context->principal->id ? 'You already have this task.' : 'Someone else has this task; ask a supervisor to reassign it.');
        }
        // the claimant must hold the stage's permission here, possibly through a delegation (attributed)
        $decision = $this->gate->authorize($context->principal, $t->permission, $this->book->resource($t));
        $this->book->assign($t, $context->principal->id, 'claimed', $context->principal->id, $decision->onBehalfOf, null, $context);
        $context->audit(new AuditEntry(action: $command->action(), entityType: 'application', entityId: $t->application_id, after: ['task_id' => $t->id, 'stage' => $t->stage_code, 'on_behalf_of' => $decision->onBehalfOf]));

        return ['data' => $this->queries->present($t)];
    }
}

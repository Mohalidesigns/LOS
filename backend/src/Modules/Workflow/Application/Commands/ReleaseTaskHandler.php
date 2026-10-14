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

final class ReleaseTaskHandler implements CommandHandler
{
    public function __construct(private readonly WorkflowFile $file, private readonly TaskBook $book, private readonly TaskQueries $queries) {}

    /** @return array{data: array<string, mixed>} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof ReleaseTask);
        $t = WorkflowTask::query()->lockForUpdate()->findOrFail($this->file->task($command->taskId)->id);
        if ($t->status !== 'open' || $t->assignee_id !== $context->principal->id) {
            throw new DomainRuleViolation('Only the current owner can release an open task.');
        }
        $this->book->assign($t, null, 'released', $context->principal->id, null, $command->reason, $context);
        $context->audit(new AuditEntry(action: $command->action(), entityType: 'application', entityId: $t->application_id, after: ['task_id' => $t->id, 'stage' => $t->stage_code], reasonText: $command->reason));

        return ['data' => $this->queries->present($t)];
    }
}

<?php

declare(strict_types=1);

namespace Fundly\Modules\Workflow\Application\Commands;

use Fundly\Modules\Workflow\Application\TaskBook;
use Fundly\Modules\Workflow\Application\WorkflowFile;
use Fundly\Modules\Workflow\Infrastructure\Models\WorkflowTask;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;

/**
 * Keeps the single stage task in step with the canonical status (TRD §6.1):
 * a hold pauses the task's clock and leaving the hold for the same status
 * resumes it (FR-WFL-008); any other move closes the task (cancelled when
 * the application ends) and opens the next stage's task if that stage has
 * an assignment.
 */
final class SyncWorkflowTaskHandler implements CommandHandler
{
    private const ENDING = ['declined', 'expired', 'withdrawn', 'cancelled', 'booked'];

    public function __construct(private readonly WorkflowFile $file, private readonly TaskBook $book) {}

    /** @return array{outcome: string, task_id: ?string} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof SyncWorkflowTask);
        $app = $this->file->application($command->applicationId);
        [$definition, $instance] = $this->file->definition($app);
        $open = WorkflowTask::query()->lockForUpdate()->where('application_id', $app->id)->where('status', 'open')->first();
        $outcome = 'unchanged';
        if ($command->to === 'on_hold') {
            if ($open !== null) {
                $this->book->pause($open);
                $outcome = 'paused';
            }
        } elseif ($open !== null && $open->canonical_status === $command->to) {
            if ($command->from === 'on_hold') {
                $this->book->resume($open, $definition->calendar());
                $outcome = 'resumed';
            }
        } else {
            if ($open !== null) {
                $ending = in_array($command->to, self::ENDING, true) && $command->to !== 'booked';
                $this->book->close($open, $ending ? 'cancelled' : 'completed', "status:{$command->to}", $command->actorId);
                $outcome = 'closed';
            }
            $stage = $definition->stageFor($command->to);
            if ($stage !== null && $stage['assignment'] !== null) {
                /** @var array{code: string, name: string, status: string, assignment: array{permission: string, strategy: string}, sla: ?array{warn_hours: int, breach_hours: int, escalate_to: string}} $stage */
                $open = $this->book->open($app, $stage, $definition->calendar(), $context);
                $outcome = $outcome === 'closed' ? 'advanced' : 'opened';
            } else {
                $open = null;
            }
        }
        if ($outcome !== 'unchanged') {
            $context->audit(new AuditEntry(action: $command->action(), entityType: 'application', entityId: $app->id, after: [
                'outcome' => $outcome, 'from' => $command->from, 'to' => $command->to, 'task_id' => $open?->id, 'stage' => $open?->stage_code,
                'assignee_id' => $open?->assignee_id, 'workflow' => ['key' => $instance->workflow_key, 'source' => $instance->source, 'version_no' => $instance->version_no],
            ]));
        }

        return ['outcome' => $outcome, 'task_id' => $open?->id];
    }
}

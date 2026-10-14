<?php

declare(strict_types=1);

namespace Fundly\Modules\Workflow\Application\Commands;

use Fundly\Modules\Workflow\Application\WorkflowFile;
use Fundly\Modules\Workflow\Contracts\Events\TaskSlaChanged;
use Fundly\Modules\Workflow\Infrastructure\Models\WorkflowTask;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;

/** FR-WFL-006: warn the owner at the warn threshold, escalate at breach. Paused (on hold) clocks do not move. */
final class EvaluateSlaClocksHandler implements CommandHandler
{
    public function __construct(private readonly WorkflowFile $file) {}

    /** @return array{at_risk: int, breached: int} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof EvaluateSlaClocks);
        $now = $this->file->now();
        $counts = ['at_risk' => 0, 'breached' => 0];
        $due = WorkflowTask::query()->where('status', 'open')->whereNull('paused_at')->whereIn('sla_state', ['on_track', 'at_risk'])
            ->where('warn_at', '<=', $now)->orderBy('warn_at')->limit(500)->lockForUpdate()->get();
        foreach ($due as $t) {
            $state = $t->breach_at !== null && $t->breach_at->lessThanOrEqualTo($now) ? 'breached' : 'at_risk';
            if ($state === $t->sla_state) {
                continue;
            }
            $t->forceFill($state === 'breached' ? ['sla_state' => 'breached', 'breached_at' => $now] : ['sla_state' => 'at_risk', 'warned_at' => $now])->save();
            $counts[$state]++;
            $dueAt = $state === 'breached' ? $t->breach_at : $t->warn_at;
            $context->raise(new TaskSlaChanged($context->principal->tenantId, $t->application_id, $t->id, $state, $t->stage_name, $t->assignee_id, $t->permission, (string) ($t->escalate_to ?? 'task:reassign'), (string) $dueAt?->toIso8601String()));
            $context->audit(new AuditEntry(action: $state === 'breached' ? 'workflow.sla_breached' : 'workflow.sla_at_risk', entityType: 'application', entityId: $t->application_id, after: [
                'task_id' => $t->id, 'stage' => $t->stage_code, 'assignee_id' => $t->assignee_id, 'escalate_to' => $state === 'breached' ? $t->escalate_to : null,
            ]));
        }

        return $counts;
    }
}

<?php

declare(strict_types=1);

namespace Fundly\Modules\Workflow\Application;

use Fundly\Modules\Application\Contracts\ApplicationSummary;
use Fundly\Modules\Party\Contracts\PartyDirectory;
use Fundly\Modules\Workflow\Contracts\Events\TaskAssigned;
use Fundly\Modules\Workflow\Domain\BusinessCalendar;
use Fundly\Modules\Workflow\Infrastructure\Models\TaskAssignment;
use Fundly\Modules\Workflow\Infrastructure\Models\WorkflowTask;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Security\ResourceAttributes;
use Illuminate\Support\Carbon;

/**
 * Task lifecycle shared by the workflow commands: open a stage task with
 * its SLA clock, close it, pause and resume it on hold (FR-WFL-008), and
 * assign it with an attributed history (FR-WFL-005).
 */
final class TaskBook
{
    public function __construct(private readonly WorkflowFile $file, private readonly TaskRouter $router, private readonly PartyDirectory $parties) {}

    /**
     * @param  array{code: string, name: string, status: string, assignment: array{permission: string, strategy: string}, sla: ?array{warn_hours: int, breach_hours: int, escalate_to: string}}  $stage
     */
    public function open(ApplicationSummary $app, array $stage, BusinessCalendar $calendar, CommandContext $context): WorkflowTask
    {
        $now = $this->file->now();
        $primary = (string) array_search('primary', $app->applicants, true);
        $t = new WorkflowTask;
        $t->forceFill([
            'application_id' => $app->id, 'application_reference' => $app->reference, 'applicant_name' => $primary === '' ? null : $this->parties->find($primary)?->displayName,
            'legal_entity_id' => $app->legalEntityId, 'org_unit_id' => $app->orgUnitId, 'product_id' => $app->productId, 'segment' => $app->segment,
            'stage_code' => $stage['code'], 'stage_name' => $stage['name'], 'canonical_status' => $stage['status'],
            'permission' => $stage['assignment']['permission'], 'strategy' => $stage['assignment']['strategy'], 'status' => 'open',
            'warn_minutes' => $stage['sla'] === null ? null : $stage['sla']['warn_hours'] * 60, 'breach_minutes' => $stage['sla'] === null ? null : $stage['sla']['breach_hours'] * 60,
            'warn_at' => $stage['sla'] === null ? null : Carbon::instance($calendar->addBusinessMinutes($now->toDateTimeImmutable(), $stage['sla']['warn_hours'] * 60)),
            'breach_at' => $stage['sla'] === null ? null : Carbon::instance($calendar->addBusinessMinutes($now->toDateTimeImmutable(), $stage['sla']['breach_hours'] * 60)),
            'sla_state' => $stage['sla'] === null ? 'none' : 'on_track', 'escalate_to' => $stage['sla']['escalate_to'] ?? null,
            'paused_minutes' => 0, 'opened_at' => $now,
        ])->save();
        $assignee = $this->router->route($t->strategy, $t->permission, $app, $t->resource('application'), $t->stage_code);
        if ($assignee !== null) {
            $this->assign($t, $assignee, 'routed', $context->principal->id, null, null, $context);
        }

        return $t;
    }

    public function close(WorkflowTask $t, string $status, string $reason, string $actor): void
    {
        $t->forceFill(['status' => $status, 'closed_at' => $this->file->now(), 'closed_by' => $actor, 'close_reason' => $reason])->save();
    }

    public function pause(WorkflowTask $t): void
    {
        if ($t->paused_at === null) {
            $t->forceFill(['paused_at' => $this->file->now()])->save();
        }
    }

    /** Resume after hold: the deadlines move by the business time spent on hold. */
    public function resume(WorkflowTask $t, BusinessCalendar $calendar): void
    {
        if ($t->paused_at === null) {
            return;
        }
        $paused = $calendar->businessMinutesBetween($t->paused_at->toDateTimeImmutable(), $this->file->now()->toDateTimeImmutable());
        $shift = static fn (?Carbon $at): ?Carbon => $at === null ? null : Carbon::instance($calendar->addBusinessMinutes($at->toDateTimeImmutable(), $paused));
        $t->forceFill(['warn_at' => $shift($t->warn_at), 'breach_at' => $shift($t->breach_at), 'paused_at' => null, 'paused_minutes' => $t->paused_minutes + $paused])->save();
    }

    public function assign(WorkflowTask $t, ?string $to, string $kind, string $actor, ?string $onBehalfOf, ?string $reason, CommandContext $context): void
    {
        $from = $t->assignee_id;
        $t->forceFill(['assignee_id' => $to, 'assigned_at' => $to === null ? null : $this->file->now()])->save();
        $h = new TaskAssignment;
        $h->forceFill(['task_id' => $t->id, 'kind' => $kind, 'from_user_id' => $from, 'to_user_id' => $to, 'by_actor' => $actor, 'on_behalf_of' => $onBehalfOf, 'reason' => $reason, 'at' => $this->file->now()])->save();
        if ($to !== null && $to !== $actor) {
            $context->raise(new TaskAssigned($context->principal->tenantId, $t->application_id, $t->id, $h->id, $to, $t->stage_name, $actor));
        }
    }

    public function canWork(string $userId, WorkflowTask $t): bool
    {
        return $this->router->canWork($userId, $t->permission, $t->resource('application'));
    }

    public function resource(WorkflowTask $t): ResourceAttributes
    {
        return $t->resource('application');
    }
}

<?php

declare(strict_types=1);

namespace Fundly\Modules\Workflow\Application;

use Fundly\Modules\Access\Contracts\AuthorityDirectory;
use Fundly\Modules\Workflow\Infrastructure\Models\TaskAssignment;
use Fundly\Modules\Workflow\Infrastructure\Models\TaskView;
use Fundly\Modules\Workflow\Infrastructure\Models\WorkflowInstance;
use Fundly\Modules\Workflow\Infrastructure\Models\WorkflowTask;
use Fundly\Shared\Security\AuthorizationGate;
use Fundly\Shared\Security\Principal;
use Fundly\Shared\Security\ResourceAttributes;
use Illuminate\Database\Eloquent\Builder;

/** The consolidated task inbox (FR-WFL-009) and each application's workflow record. */
final class TaskQueries
{
    private const VIEWS = ['mine', 'queue', 'team'];

    public function __construct(private readonly WorkflowFile $file, private readonly AuthorizationGate $gate, private readonly AuthorityDirectory $directory) {}

    /**
     * @param  array<string, mixed>  $filter  view (mine|queue|team), stage, sla (on_track|at_risk|breached|paused)
     * @return array{data: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function inbox(Principal $principal, array $filter, string $sort): array
    {
        $view = in_array($filter['view'] ?? null, self::VIEWS, true) ? (string) $filter['view'] : 'mine';
        $q = $this->filtered(WorkflowTask::query()->where('status', 'open'), $filter);
        $tasks = match ($view) {
            'mine' => $q->where('assignee_id', $principal->id)->get()->all(),
            'queue' => array_values(array_filter($q->whereNull('assignee_id')->limit(300)->get()->all(), fn (WorkflowTask $t): bool => $this->can($principal, $t->permission, $t->resource('application')))),
            default => array_values(array_filter($q->limit(500)->get()->all(), fn (WorkflowTask $t): bool => $this->can($principal, 'task:reassign', $t->resource()))),
        };
        usort($tasks, $sort === '-opened_at'
            ? static fn (WorkflowTask $a, WorkflowTask $b): int => $b->opened_at <=> $a->opened_at
            : static fn (WorkflowTask $a, WorkflowTask $b): int => [$a->breach_at === null, $a->breach_at] <=> [$b->breach_at === null, $b->breach_at]);
        $mine = WorkflowTask::query()->where('status', 'open')->where('assignee_id', $principal->id);

        return [
            'data' => $this->presentMany(array_slice($tasks, 0, 200)),
            'meta' => [
                'view' => $view,
                'counts' => ['mine' => (clone $mine)->count(), 'mine_breached' => (clone $mine)->where('sla_state', 'breached')->count()],
                'can_reassign' => $this->can($principal, 'task:reassign', null),
            ],
        ];
    }

    /** @return array<string, mixed> the workflow, current task and task history of one application */
    public function forApplication(string $applicationId): array
    {
        $instance = WorkflowInstance::query()->where('application_id', $applicationId)->first();
        $tasks = WorkflowTask::query()->where('application_id', $applicationId)->orderByDesc('opened_at')->get();
        $history = TaskAssignment::query()->whereIn('task_id', $tasks->pluck('id'))->orderBy('at')->get()->groupBy('task_id');
        $people = [];
        foreach ($history->flatten() as $h) {
            assert($h instanceof TaskAssignment);
            array_push($people, (string) $h->from_user_id, (string) $h->to_user_id, $h->by_actor, (string) $h->on_behalf_of);
        }
        $names = $this->directory->names(array_values(array_unique(array_filter($people, static fn (string $p): bool => preg_match('/^[0-9a-f-]{36}$/', $p) === 1))));
        $presented = $this->presentMany(array_values($tasks->all()));
        foreach ($presented as $i => $p) {
            $presented[$i]['history'] = array_values(array_map(static fn (TaskAssignment $h): array => [
                'kind' => $h->kind, 'from' => $h->from_user_id === null ? null : ['id' => $h->from_user_id, 'name' => $names[$h->from_user_id] ?? null],
                'to' => $h->to_user_id === null ? null : ['id' => $h->to_user_id, 'name' => $names[$h->to_user_id] ?? null],
                'by' => ['id' => $h->by_actor, 'name' => $names[$h->by_actor] ?? null], 'on_behalf_of' => $h->on_behalf_of === null ? null : ['id' => $h->on_behalf_of, 'name' => $names[$h->on_behalf_of] ?? null],
                'reason' => $h->reason, 'at' => $h->at->toIso8601String(),
            ], ($history[$p['id']] ?? collect())->all()));
        }
        $definition = $instance === null ? null : $this->file->content($instance);

        return [
            'application_id' => $applicationId,
            'workflow' => $instance === null || $definition === null ? null : [
                'key' => $instance->workflow_key, 'name' => $definition->name(), 'source' => $instance->source, 'version_id' => $instance->version_id, 'version_no' => $instance->version_no,
                'pinned_at' => $instance->pinned_at->toIso8601String(), 'migrated_at' => $instance->migrated_at?->toIso8601String(),
            ],
            'current' => array_values(array_filter($presented, static fn (array $p): bool => $p['status'] === 'open'))[0] ?? null,
            'tasks' => $presented,
        ];
    }

    /**
     * People who could take a task: holders of its permission in its scope (FR-WFL-005).
     *
     * @return list<array{id: string, name: ?string, open_tasks: int}>
     */
    public function candidates(WorkflowTask $t): array
    {
        return $this->people($t->permission, $t->resource('application'));
    }

    /**
     * People who can see an application (to @mention them).
     *
     * @return list<array{id: string, name: ?string, open_tasks: int}>
     */
    public function people(string $permission, ResourceAttributes $resource): array
    {
        $ids = array_values(array_unique(array_map(static fn ($g): string => $g->userId, $this->directory->holders($permission, $resource))));
        $names = $this->directory->names($ids);
        $load = $ids === [] ? [] : WorkflowTask::query()->where('status', 'open')->whereIn('assignee_id', $ids)->selectRaw('assignee_id, count(*) as n')->groupBy('assignee_id')->pluck('n', 'assignee_id')->all();
        $out = array_map(static fn (string $id): array => ['id' => $id, 'name' => $names[$id] ?? null, 'open_tasks' => (int) ($load[$id] ?? 0)], $ids);
        usort($out, static fn (array $a, array $b): int => strcmp((string) $a['name'], (string) $b['name']));

        return array_slice($out, 0, 200);
    }

    /** @return array<string, mixed> */
    public function present(WorkflowTask $t): array
    {
        return $this->presentMany([$t])[0];
    }

    /** @return array{data: list<array<string, mixed>>} */
    public function views(Principal $principal): array
    {
        return ['data' => array_values(TaskView::query()->where('user_id', $principal->id)->orderBy('name')->get()->map(fn (TaskView $v): array => $this->view($v))->all())];
    }

    /** @return array{id: string, name: string, filters: object} filters always serialise as a JSON object */
    public function view(TaskView $v): array
    {
        return ['id' => $v->id, 'name' => $v->name, 'filters' => (object) $v->filters];
    }

    /**
     * @param  list<WorkflowTask>  $tasks
     * @return list<array<string, mixed>>
     */
    private function presentMany(array $tasks): array
    {
        $names = $this->directory->names(array_values(array_unique(array_filter(array_map(static fn (WorkflowTask $t): ?string => $t->assignee_id, $tasks)))));

        return array_map(static fn (WorkflowTask $t): array => [
            'id' => $t->id,
            'application' => ['id' => $t->application_id, 'reference' => $t->application_reference, 'applicant_name' => $t->applicant_name],
            'stage' => ['code' => $t->stage_code, 'name' => $t->stage_name, 'status' => $t->canonical_status],
            'permission' => $t->permission, 'strategy' => $t->strategy, 'status' => $t->status,
            'assignee' => $t->assignee_id === null ? null : ['id' => $t->assignee_id, 'name' => $names[$t->assignee_id] ?? null],
            'sla' => [
                'state' => $t->status === 'open' && $t->paused_at !== null ? 'paused' : $t->sla_state,
                'warn_at' => $t->warn_at?->toIso8601String(), 'breach_at' => $t->breach_at?->toIso8601String(), 'paused_minutes' => $t->paused_minutes,
            ],
            'opened_at' => $t->opened_at->toIso8601String(), 'closed_at' => $t->closed_at?->toIso8601String(), 'close_reason' => $t->close_reason,
        ], $tasks);
    }

    /**
     * @param  Builder<WorkflowTask>  $q
     * @param  array<string, mixed>  $filter
     * @return Builder<WorkflowTask>
     */
    private function filtered(Builder $q, array $filter): Builder
    {
        if (is_string($filter['stage'] ?? null) && $filter['stage'] !== '') {
            $q->where('stage_code', $filter['stage']);
        }
        $sla = $filter['sla'] ?? null;
        if ($sla === 'paused') {
            $q->whereNotNull('paused_at');
        } elseif (in_array($sla, ['on_track', 'at_risk', 'breached'], true)) {
            $q->whereNull('paused_at')->where('sla_state', $sla);
        }

        return $q;
    }

    private function can(Principal $principal, string $permission, ?ResourceAttributes $resource): bool
    {
        return $this->gate->check($principal, $permission, $resource)->allowed;
    }
}

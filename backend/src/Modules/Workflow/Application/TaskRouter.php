<?php

declare(strict_types=1);

namespace Fundly\Modules\Workflow\Application;

use Fundly\Modules\Access\Contracts\AuthorityDirectory;
use Fundly\Modules\Application\Contracts\ApplicationSummary;
use Fundly\Modules\Workflow\Infrastructure\Models\WorkflowTask;
use Fundly\Shared\Security\ResourceAttributes;

/**
 * Push assignment (FR-WFL-003/004) among the users who hold the stage's
 * permission in the application's scope (role, branch, product, segment,
 * amount band through scope limits): `originator` keeps the case with the
 * officer who raised it; `round_robin` rotates in stable order;
 * `least_loaded` picks whoever has the fewest open tasks. Only the
 * originator strategy gives a task to the originator; `queue` leaves it for
 * someone to claim.
 */
final class TaskRouter
{
    public function __construct(private readonly AuthorityDirectory $authority) {}

    public function route(string $strategy, string $permission, ApplicationSummary $app, ResourceAttributes $resource, string $stageCode): ?string
    {
        if ($strategy === 'queue') {
            return null;
        }
        if ($strategy === 'originator') {
            return $this->authority->grantsFor($app->originatorId, $permission, $resource) === [] ? null : $app->originatorId;
        }
        $holders = array_values(array_diff($this->eligible($permission, $resource), [$app->originatorId]));
        if ($holders === []) {
            return null;
        }
        sort($holders);
        if ($strategy === 'least_loaded') {
            $load = WorkflowTask::query()->where('status', 'open')->whereIn('assignee_id', $holders)->selectRaw('assignee_id, count(*) as n')->groupBy('assignee_id')->pluck('n', 'assignee_id')->all();
            usort($holders, static fn (string $a, string $b): int => ((int) ($load[$a] ?? 0) <=> (int) ($load[$b] ?? 0)) ?: strcmp($a, $b));

            return $holders[0];
        }
        $last = WorkflowTask::query()->where('stage_code', $stageCode)->whereNotNull('assignee_id')->orderByDesc('assigned_at')->value('assignee_id');
        foreach ($holders as $h) {
            if ($last === null || strcmp($h, (string) $last) > 0) {
                return $h;
            }
        }

        return $holders[0];
    }

    /** @return list<string> active users holding the permission for the resource */
    public function eligible(string $permission, ResourceAttributes $resource): array
    {
        return array_values(array_unique(array_map(static fn ($g): string => $g->userId, $this->authority->holders($permission, $resource))));
    }

    public function canWork(string $userId, string $permission, ResourceAttributes $resource): bool
    {
        return $this->authority->grantsFor($userId, $permission, $resource) !== [];
    }
}

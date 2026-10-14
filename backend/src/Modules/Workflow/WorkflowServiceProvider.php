<?php

declare(strict_types=1);

namespace Fundly\Modules\Workflow;

use Fundly\Modules\Application\Contracts\Events\ApplicationStatusChanged;
use Fundly\Modules\Platform\Contracts\ConfigTypeCatalogue;
use Fundly\Modules\Workflow\Application\IntakeAutomation;
use Fundly\Modules\Workflow\Application\TaskBook;
use Fundly\Modules\Workflow\Application\TaskRouter;
use Fundly\Modules\Workflow\Application\WorkflowFile;
use Fundly\Modules\Workflow\Application\WorkflowProgression;
use Fundly\Modules\Workflow\Console\EvaluateSlaCommand;
use Fundly\Modules\Workflow\Domain\WorkflowDefinition;
use Fundly\Modules\Workflow\Infrastructure\Models\WorkflowTask;
use Fundly\Shared\Security\ResourceAttributes;
use Fundly\Shared\Security\ResourceResolver;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;

/**
 * M10 Workflow (P1-WFL-01..03): per-product workflow definitions with
 * version pinning, stage tasks with routing, pull/push queues, SLA clocks
 * with pause on hold and escalation, the consolidated inbox, case notes.
 */
final class WorkflowServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(WorkflowFile::class);
        $this->app->scoped(TaskRouter::class);
        $this->app->scoped(TaskBook::class);
    }

    public function boot(Dispatcher $events, ConfigTypeCatalogue $types, ResourceResolver $resources): void
    {
        $types->register(WorkflowFile::TYPE, 'Workflow (key = the product binding, e.g. sme-standard): stages mapped to canonical statuses, each with assignment (permission + queue/round_robin/least_loaded/originator), business-hour SLA and escalation, and automated intake stages (FR-WFL-001/003/004/006/011).', static function (array $content): void {
            WorkflowDefinition::validate($content);
        });
        $resources->register('workflow_task', static function (string $id): ?ResourceAttributes {
            $t = preg_match('/^[0-9a-f-]{36}$/', $id) === 1 ? WorkflowTask::query()->find($id) : null;

            return $t?->resource();
        });
        // the task sync must see a status before intake automation moves past it
        $events->listen(ApplicationStatusChanged::class, [WorkflowProgression::class, 'onStatusChanged']);
        $events->listen(ApplicationStatusChanged::class, [IntakeAutomation::class, 'onStatusChanged']);
        if ($this->app->runningInConsole()) {
            $this->commands([EvaluateSlaCommand::class]);
        }
    }
}

<?php

declare(strict_types=1);

namespace Fundly\Modules\Workflow\Application;

use Fundly\Modules\Application\Contracts\ApplicationReader;
use Fundly\Modules\Application\Contracts\ApplicationSummary;
use Fundly\Modules\Platform\Contracts\ActiveConfiguration;
use Fundly\Modules\Product\Contracts\ProductCatalogue;
use Fundly\Modules\Workflow\Domain\DefaultWorkflow;
use Fundly\Modules\Workflow\Domain\WorkflowDefinition;
use Fundly\Modules\Workflow\Infrastructure\Models\WorkflowInstance;
use Fundly\Modules\Workflow\Infrastructure\Models\WorkflowTask;
use Fundly\Shared\Clock\Clock;
use Fundly\Shared\Exceptions\NotFound;
use Fundly\Shared\Security\ResourceAttributes;
use Illuminate\Support\Carbon;

/**
 * Resolves and pins the workflow an application runs on (FR-WFL-001/012):
 * the product's bound `workflow.definition` key, its active tenant version
 * at the application's first workflow event, else the built-in default.
 * Once pinned, the version is used until an administrator migrates it.
 */
final class WorkflowFile
{
    public const TYPE = 'workflow.definition';

    public function __construct(
        private readonly ApplicationReader $applications,
        private readonly ProductCatalogue $products,
        private readonly ActiveConfiguration $config,
        private readonly Clock $clock,
    ) {}

    public function application(string $id): ApplicationSummary
    {
        return $this->applications->find($id) ?? throw new NotFound('Application not found.');
    }

    public function task(string $id): WorkflowTask
    {
        $t = preg_match('/^[0-9a-f-]{36}$/', $id) === 1 ? WorkflowTask::query()->find($id) : null;

        return $t ?? throw new NotFound('Task not found.');
    }

    public function workflowKey(ApplicationSummary $app): string
    {
        return $this->products->version($app->productVersionId)?->binding('workflow') ?? 'standard';
    }

    /** @return array{0: WorkflowDefinition, 1: WorkflowInstance} the pinned definition, pinning it now if needed */
    public function definition(ApplicationSummary $app): array
    {
        $instance = WorkflowInstance::query()->where('application_id', $app->id)->first();
        if ($instance === null) {
            $key = $this->workflowKey($app);
            $v = $this->config->activeVersion(self::TYPE, $key);
            $instance = new WorkflowInstance;
            $instance->forceFill([
                'application_id' => $app->id, 'workflow_key' => $key, 'source' => $v === null ? 'default' : 'tenant',
                'version_id' => $v?->id, 'version_no' => $v?->versionNo, 'pinned_at' => $this->now(),
            ])->save();
        }

        return [$this->content($instance), $instance];
    }

    /** The definition an application runs on, without pinning (read-only callers). */
    public function peek(ApplicationSummary $app): WorkflowDefinition
    {
        $instance = WorkflowInstance::query()->where('application_id', $app->id)->first();
        if ($instance !== null) {
            return $this->content($instance);
        }
        $v = $this->config->activeVersion(self::TYPE, $this->workflowKey($app));

        return WorkflowDefinition::of($v === null ? DefaultWorkflow::standard() : $v->content);
    }

    public function content(WorkflowInstance $instance): WorkflowDefinition
    {
        $v = $instance->version_id === null ? null : $this->config->version($instance->version_id);

        return WorkflowDefinition::of($v === null ? DefaultWorkflow::standard() : $v->content);
    }

    public function resource(ApplicationSummary $app): ResourceAttributes
    {
        return new ResourceAttributes(legalEntityId: $app->legalEntityId, orgUnitId: $app->orgUnitId, productId: $app->productId, segment: $app->segment, entityType: 'application', entityId: $app->id);
    }

    public function now(): Carbon
    {
        return Carbon::instance($this->clock->now());
    }
}

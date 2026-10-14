<?php

declare(strict_types=1);

namespace Fundly\Modules\Workflow\Application\Commands;

use Fundly\Modules\Platform\Contracts\ActiveConfiguration;
use Fundly\Modules\Workflow\Application\TaskQueries;
use Fundly\Modules\Workflow\Application\WorkflowFile;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Exceptions\DomainRuleViolation;

/** FR-WFL-012: in-flight work stays on its version unless an administrator migrates it, with a reason. Takes effect from the next stage. */
final class MigrateWorkflowHandler implements CommandHandler
{
    public function __construct(private readonly WorkflowFile $file, private readonly ActiveConfiguration $config, private readonly TaskQueries $queries) {}

    /** @return array{data: array<string, mixed>} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof MigrateWorkflow);
        $app = $this->file->application($command->applicationId);
        [, $instance] = $this->file->definition($app);
        $v = $this->config->activeVersion(WorkflowFile::TYPE, $instance->workflow_key);
        if ($v === null || $v->id === $instance->version_id) {
            throw new DomainRuleViolation('The application is already on the active workflow version.');
        }
        $before = ['source' => $instance->source, 'version_id' => $instance->version_id, 'version_no' => $instance->version_no];
        $instance->forceFill([
            'source' => 'tenant', 'migrated_from_version_id' => $instance->version_id, 'version_id' => $v->id, 'version_no' => $v->versionNo,
            'migrated_by' => $context->principal->id, 'migrated_at' => $this->file->now(),
        ])->save();
        $context->audit(new AuditEntry(action: $command->action(), entityType: 'application', entityId: $app->id, before: $before, after: ['source' => 'tenant', 'version_id' => $v->id, 'version_no' => $v->versionNo], reasonText: $command->reason));

        return ['data' => $this->queries->forApplication($app->id)];
    }
}

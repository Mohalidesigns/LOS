<?php

declare(strict_types=1);

namespace Fundly\Modules\Platform\Application\Commands\Config;

use Fundly\Modules\Platform\Application\Config\ConfigPresenter;
use Fundly\Modules\Platform\Application\Config\ConfigTypeRegistry;
use Fundly\Modules\Platform\Infrastructure\Models\ConfigArtifact;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Exceptions\CodedConflict;
use Fundly\Shared\Exceptions\NotFound;

final class CreateConfigArtifactHandler implements CommandHandler
{
    public function __construct(private readonly ConfigTypeRegistry $types)
    {
    }

    /** @return array{data: array<string, mixed>} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof CreateConfigArtifact);
        if (! $this->types->has($command->type)) {
            throw new NotFound("Unknown configuration type {$command->type}.");
        }
        if (ConfigArtifact::query()->where(['type' => $command->type, 'key' => $command->key])->exists()) {
            throw new CodedConflict('config-artifact-exists', 'This configuration artefact already exists.');
        }
        $a = new ConfigArtifact;
        $a->forceFill(['type' => $command->type, 'key' => $command->key, 'name' => $command->name, 'description' => $command->description])->save();
        $a->refresh();
        $context->audit(new AuditEntry(action: $command->action(), entityType: 'config_artifact', entityId: $a->id, after: ConfigPresenter::artifact($a)));

        return ['data' => ConfigPresenter::artifact($a)];
    }
}

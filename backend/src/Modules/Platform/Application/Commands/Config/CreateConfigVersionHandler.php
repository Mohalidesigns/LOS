<?php

declare(strict_types=1);

namespace Fundly\Modules\Platform\Application\Commands\Config;

use Fundly\Modules\Platform\Application\Config\ConfigPresenter;
use Fundly\Modules\Platform\Application\Config\ConfigTypeRegistry;
use Fundly\Modules\Platform\Infrastructure\Models\ConfigArtifact;
use Fundly\Modules\Platform\Infrastructure\Models\ConfigVersion;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Json\CanonicalJson;

final class CreateConfigVersionHandler implements CommandHandler
{
    public function __construct(private readonly ConfigTypeRegistry $types)
    {
    }

    /** @return array{data: array<string, mixed>, etag: string} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof CreateConfigVersion);
        $artifact = ConfigArtifact::query()->lockForUpdate()->findOrFail($command->artifactId);
        $this->types->validate($artifact->type, $command->content);
        $next = ((int) ConfigVersion::query()->where('artifact_id', $artifact->id)->max('version_no')) + 1;

        $v = new ConfigVersion;
        $v->forceFill([
            'artifact_id' => $artifact->id,
            'version_no' => $next,
            'status' => 'draft',
            'content' => $command->content,
            'content_hash' => CanonicalJson::hash($command->content),
            'notes' => $command->notes,
            'created_by' => $context->principal->id,
        ])->save();
        $v->refresh();
        $context->audit(new AuditEntry(action: $command->action(), entityType: 'config_version', entityId: $v->id, after: ConfigPresenter::version($v)));

        return ['data' => ConfigPresenter::version($v), 'etag' => ConfigPresenter::etag($v)];
    }
}

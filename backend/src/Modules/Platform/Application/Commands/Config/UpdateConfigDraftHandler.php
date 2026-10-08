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
use Fundly\Shared\Exceptions\CodedConflict;
use Fundly\Shared\Http\ETag;
use Fundly\Shared\Json\CanonicalJson;

final class UpdateConfigDraftHandler implements CommandHandler
{
    public function __construct(private readonly ConfigTypeRegistry $types) {}

    /** @return array{data: array<string, mixed>, etag: string} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof UpdateConfigDraft);
        $v = ConfigVersion::query()->lockForUpdate()->findOrFail($command->versionId);
        ETag::assertHeader($command->ifMatch, ConfigPresenter::etag($v));
        if ($v->status !== 'draft') {
            throw new CodedConflict('config-version-immutable', "Version {$v->version_no} is {$v->status}; only drafts can be edited.");
        }
        $artifact = ConfigArtifact::query()->findOrFail($v->artifact_id);
        $this->types->validate($artifact->type, $command->content);
        $before = ConfigPresenter::version($v);
        $v->forceFill(['content' => $command->content, 'content_hash' => CanonicalJson::hash($command->content), 'notes' => $command->notes])->save();
        $context->audit(new AuditEntry(action: $command->action(), entityType: 'config_version', entityId: $v->id, before: $before, after: ConfigPresenter::version($v)));

        return ['data' => ConfigPresenter::version($v), 'etag' => ConfigPresenter::etag($v)];
    }
}

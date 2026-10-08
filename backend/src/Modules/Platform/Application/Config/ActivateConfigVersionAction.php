<?php

declare(strict_types=1);

namespace Fundly\Modules\Platform\Application\Config;

use Fundly\Modules\Access\Contracts\ChangeAction;
use Fundly\Modules\Platform\Infrastructure\Models\ConfigArtifact;
use Fundly\Modules\Platform\Infrastructure\Models\ConfigVersion;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\Payload;
use Fundly\Shared\Clock\Clock;
use Fundly\Shared\Exceptions\DomainRuleViolation;
use Fundly\Shared\Exceptions\NotFound;
use Fundly\Shared\Json\CanonicalJson;
use Fundly\Shared\Security\Principal;
use Fundly\Shared\Security\ResourceRef;

/**
 * Makes an approved version active, superseding the current one. Rollback is
 * the same action applied to a previously active (superseded) version, so it
 * is equally maker-checked and audited. Content never changes: only status.
 */
final class ActivateConfigVersionAction implements ChangeAction
{
    public const TYPE = 'platform.config.activate';

    public function __construct(private readonly Clock $clock) {}

    public function type(): string
    {
        return self::TYPE;
    }

    public function makerPermission(): string
    {
        return 'config:activate_request';
    }

    public function checkerPermission(): string
    {
        return 'config:activate_approve';
    }

    public function entity(array $payload): ResourceRef
    {
        return new ResourceRef('config_version', Payload::string($payload, 'config_version_id'));
    }

    public function validate(array $payload, Principal $maker): void
    {
        $v = ConfigVersion::query()->find(Payload::optionalString($payload, 'config_version_id'));
        if (! $v instanceof ConfigVersion) {
            throw new NotFound('Configuration version not found.');
        }
        $rollback = ($payload['rollback'] ?? false) === true;
        $allowed = $rollback ? ['superseded'] : ['approved'];
        if (! in_array($v->status, $allowed, true)) {
            throw new DomainRuleViolation($rollback
                ? "Only a previously active (superseded) version can be rolled back to; this one is {$v->status}."
                : "Only an approved version can be activated; this one is {$v->status}.");
        }
    }

    public function fingerprint(array $payload): string
    {
        $v = ConfigVersion::query()->find(Payload::string($payload, 'config_version_id'));
        $a = $v === null ? null : ConfigArtifact::query()->find($v->artifact_id);

        return CanonicalJson::hash([
            'status' => $v?->status,
            'content_hash' => $v?->content_hash,
            'active_version_id' => $a?->active_version_id,
        ]);
    }

    public function execute(array $payload, string $changeRequestId, CommandContext $context): array
    {
        $now = $this->clock->now();
        $v = ConfigVersion::query()->lockForUpdate()->findOrFail(Payload::string($payload, 'config_version_id'));
        $artifact = ConfigArtifact::query()->lockForUpdate()->findOrFail($v->artifact_id);
        $previousId = $artifact->active_version_id;

        if ($previousId !== null) {
            ConfigVersion::query()->whereKey($previousId)->update(['status' => 'superseded', 'superseded_at' => $now, 'updated_at' => $now]);
        }
        $v->forceFill([
            'status' => 'active',
            'activated_by' => $context->principal->id,
            'activated_at' => $now,
            'activation_change_request_id' => $changeRequestId,
            'superseded_at' => null,
        ])->save();
        $artifact->forceFill(['active_version_id' => $v->id])->save();

        $rollback = ($payload['rollback'] ?? false) === true;
        $context->audit(new AuditEntry(
            action: $rollback ? 'platform.config.rolled_back' : 'platform.config.activated',
            entityType: 'config_artifact',
            entityId: $artifact->id,
            before: ['active_version_id' => $previousId],
            after: ['active_version_id' => $v->id, 'version_no' => $v->version_no, 'content_hash' => $v->content_hash, 'change_request_id' => $changeRequestId],
        ));

        return ['artifact_id' => $artifact->id, 'active_version_id' => $v->id, 'previous_version_id' => $previousId, 'rollback' => $rollback];
    }

    public function excludedCheckers(array $payload): array
    {
        // The version's author may not approve putting their own content live.
        $v = ConfigVersion::query()->find(Payload::string($payload, 'config_version_id'));

        return $v === null ? [] : [$v->created_by];
    }
}

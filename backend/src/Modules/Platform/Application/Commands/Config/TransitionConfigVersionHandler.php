<?php

declare(strict_types=1);

namespace Fundly\Modules\Platform\Application\Commands\Config;

use Fundly\Modules\Platform\Application\Config\ConfigPresenter;
use Fundly\Modules\Platform\Infrastructure\Models\ConfigVersion;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Clock\Clock;
use Fundly\Shared\Exceptions\CodedConflict;
use Fundly\Shared\Security\AccessDenied;

/**
 * draft → in_review (author) → approved | rejected (a reviewer who is neither
 * the author nor the submitter). An approved version is immutable (DB trigger).
 */
final class TransitionConfigVersionHandler implements CommandHandler
{
    private const FROM = [
        TransitionConfigVersion::SUBMIT => 'draft',
        TransitionConfigVersion::APPROVE => 'in_review',
        TransitionConfigVersion::REJECT => 'in_review',
    ];

    public function __construct(private readonly Clock $clock) {}

    /** @return array{data: array<string, mixed>, etag: string} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof TransitionConfigVersion);
        $v = ConfigVersion::query()->lockForUpdate()->findOrFail($command->versionId);
        $expected = self::FROM[$command->transition];
        if ($v->status !== $expected) {
            throw new CodedConflict('config-version-invalid-transition', "Cannot {$command->transition} a version that is {$v->status}.");
        }
        $actor = $context->principal->id;
        $now = $this->clock->now();
        $before = ConfigPresenter::version($v);

        if ($command->transition === TransitionConfigVersion::SUBMIT) {
            $v->forceFill(['status' => 'in_review', 'submitted_by' => $actor, 'submitted_at' => $now]);
        } else {
            if ($actor === $v->created_by || $actor === $v->submitted_by) {
                throw new AccessDenied($command->permission(), 'sod_conflict', null, 'Four-eyes: the reviewer must not be the author or submitter of this version.');
            }
            $v->forceFill([
                'status' => $command->transition === TransitionConfigVersion::APPROVE ? 'approved' : 'rejected',
                'reviewed_by' => $actor,
                'reviewed_at' => $now,
                'review_reason' => $command->reason,
            ]);
        }
        $v->save();
        $context->audit(new AuditEntry(action: $command->action(), entityType: 'config_version', entityId: $v->id, before: ['status' => $before['status']], after: ['status' => $v->status, 'content_hash' => $v->content_hash], reasonText: $command->reason));

        return ['data' => ConfigPresenter::version($v), 'etag' => ConfigPresenter::etag($v)];
    }
}

<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Application\Actions;

use Fundly\Modules\Access\Application\SessionRevoker;
use Fundly\Modules\Access\Contracts\ChangeAction;
use Fundly\Modules\Access\Domain\Permission;
use Fundly\Modules\Access\Infrastructure\Models\RoleAssignment;
use Fundly\Modules\Access\Infrastructure\Persistence\GrantRepository;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\Payload;
use Fundly\Shared\Clock\Clock;
use Fundly\Shared\Exceptions\DomainRuleViolation;
use Fundly\Shared\Exceptions\NotFound;
use Fundly\Shared\Json\CanonicalJson;
use Fundly\Shared\Security\Principal;
use Fundly\Shared\Security\ResourceRef;

final class RevokeRoleAssignmentAction implements ChangeAction
{
    public const TYPE = 'access.role_assignment.revoke';

    public function __construct(
        private readonly SessionRevoker $sessions,
        private readonly GrantRepository $grants,
        private readonly Clock $clock,
    ) {}

    public function type(): string
    {
        return self::TYPE;
    }

    public function makerPermission(): string
    {
        return Permission::RoleAssignmentRequest->value;
    }

    public function checkerPermission(): string
    {
        return Permission::RoleAssignmentApprove->value;
    }

    public function entity(array $payload): ResourceRef
    {
        return new ResourceRef('role_assignment', Payload::string($payload, 'role_assignment_id'));
    }

    public function validate(array $payload, Principal $maker): void
    {
        $a = RoleAssignment::query()->find(Payload::optionalString($payload, 'role_assignment_id'));
        if (! $a instanceof RoleAssignment) {
            throw new NotFound('Role assignment not found.');
        }
        if ($a->revoked_at !== null) {
            throw new DomainRuleViolation('The role assignment is already revoked.');
        }
    }

    public function fingerprint(array $payload): string
    {
        $a = RoleAssignment::query()->find(Payload::string($payload, 'role_assignment_id'));

        return CanonicalJson::hash(['revoked_at' => $a?->revoked_at?->format('c'), 'updated_at' => $a?->updated_at->format('Uu')]);
    }

    public function execute(array $payload, string $changeRequestId, CommandContext $context): array
    {
        $a = RoleAssignment::query()->findOrFail(Payload::string($payload, 'role_assignment_id'));
        $a->forceFill([
            'revoked_at' => $this->clock->now(),
            'revoked_by' => $context->principal->id,
            'revoke_change_request_id' => $changeRequestId,
        ])->save();
        $this->sessions->revokeAll($a->user_id);
        $this->grants->forget($a->user_id);

        $context->audit(new AuditEntry(
            action: 'access.role_assignment.revoked',
            entityType: 'role_assignment',
            entityId: $a->id,
            before: ['revoked_at' => null],
            after: ['user_id' => $a->user_id, 'role_id' => $a->role_id, 'revoked_at' => $a->revoked_at?->toIso8601ZuluString(), 'change_request_id' => $changeRequestId],
        ));

        return ['role_assignment_id' => $a->id, 'revoked' => true];
    }

    public function excludedCheckers(array $payload): array
    {
        $a = RoleAssignment::query()->find(Payload::string($payload, 'role_assignment_id'));

        return $a === null ? [] : [$a->user_id];
    }
}

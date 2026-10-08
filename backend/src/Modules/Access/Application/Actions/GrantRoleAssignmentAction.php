<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Application\Actions;

use DateTimeImmutable;
use Fundly\Modules\Access\Application\SessionRevoker;
use Fundly\Modules\Access\Application\SodChecker;
use Fundly\Modules\Access\Contracts\ChangeAction;
use Fundly\Modules\Access\Contracts\SodConflict;
use Fundly\Modules\Access\Domain\Permission;
use Fundly\Modules\Access\Domain\Scope;
use Fundly\Modules\Access\Infrastructure\Models\Role;
use Fundly\Modules\Access\Infrastructure\Models\RoleAssignment;
use Fundly\Modules\Access\Infrastructure\Models\User;
use Fundly\Modules\Access\Infrastructure\Persistence\GrantRepository;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\Payload;
use Fundly\Shared\Exceptions\DomainRuleViolation;
use Fundly\Shared\Exceptions\ValidationFailed;
use Fundly\Shared\Json\CanonicalJson;
use Fundly\Shared\Security\Principal;
use Fundly\Shared\Security\ResourceRef;
use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;

/**
 * Grant a role to a user with a scope and validity window (FR-SEC-004/005/008),
 * via maker-checker (FR-SEC-007), with SoD checked at request and at
 * execution (FR-SEC-006). The user is forced to re-authenticate.
 */
final class GrantRoleAssignmentAction implements ChangeAction
{
    public const TYPE = 'access.role_assignment.grant';

    public function __construct(
        private readonly SodChecker $sod,
        private readonly SessionRevoker $sessions,
        private readonly GrantRepository $grants,
        private readonly ConnectionInterface $db,
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
        return new ResourceRef('user', Payload::string($payload, 'user_id'));
    }

    public function validate(array $payload, Principal $maker): void
    {
        $user = User::query()->find(Payload::optionalString($payload, 'user_id'));
        if (! $user instanceof User || ! $user->isActive()) {
            throw ValidationFailed::with(['user_id' => 'The user does not exist or is disabled.']);
        }
        $role = Role::query()->find(Payload::optionalString($payload, 'role_id'));
        if (! $role instanceof Role) {
            throw ValidationFailed::with(['role_id' => 'The role does not exist.']);
        }
        if ($role->is_template) {
            throw new DomainRuleViolation('Library templates cannot be assigned; clone the template into a role first.');
        }
        if ($maker->id === $user->id) {
            throw new DomainRuleViolation('You cannot request a role for yourself.');
        }

        try {
            $scope = Scope::fromArray(Payload::map($payload, 'scope'));
        } catch (InvalidArgumentException $e) {
            throw ValidationFailed::with(['scope' => $e->getMessage()]);
        }
        $this->assertScopeReferencesExist($scope);

        $from = new DateTimeImmutable(Payload::string($payload, 'valid_from'));
        $toValue = Payload::optionalString($payload, 'valid_to');
        $to = $toValue !== null ? new DateTimeImmutable($toValue) : null;
        if ($to !== null && $to <= $from) {
            throw ValidationFailed::with(['valid_to' => 'valid_to must be after valid_from.']);
        }

        $conflicts = $this->sod->conflictsFor($user->id, $role->permissionCodes(), [$role->id]);
        if ($conflicts !== []) {
            throw new SodConflict($conflicts);
        }
    }

    public function fingerprint(array $payload): string
    {
        $userId = Payload::string($payload, 'user_id');
        $role = Role::query()->find(Payload::string($payload, 'role_id'));

        return CanonicalJson::hash([
            'user_status' => User::query()->whereKey($userId)->value('status'),
            'assignments' => RoleAssignment::query()->where('user_id', $userId)->whereNull('revoked_at')->orderBy('id')->pluck('id')->all(),
            'role_permissions' => $role?->permissionCodes(),
        ]);
    }

    public function execute(array $payload, string $changeRequestId, CommandContext $context): array
    {
        $validTo = Payload::optionalString($payload, 'valid_to');
        $assignment = new RoleAssignment;
        $assignment->forceFill([
            'user_id' => Payload::string($payload, 'user_id'),
            'role_id' => Payload::string($payload, 'role_id'),
            'scope' => Scope::fromArray(Payload::map($payload, 'scope'))->toArray(),
            'valid_from' => new DateTimeImmutable(Payload::string($payload, 'valid_from')),
            'valid_to' => $validTo !== null ? new DateTimeImmutable($validTo) : null,
            'granted_by' => $context->principal->id,
            'change_request_id' => $changeRequestId,
        ])->save();

        $revoked = $this->sessions->revokeAll($assignment->user_id);
        $this->grants->forget($assignment->user_id);

        $context->audit(new AuditEntry(
            action: 'access.role_assignment.granted',
            entityType: 'role_assignment',
            entityId: $assignment->id,
            after: [
                'user_id' => $assignment->user_id,
                'role_id' => $assignment->role_id,
                'scope' => $assignment->scope,
                'valid_from' => Payload::string($payload, 'valid_from'),
                'valid_to' => Payload::optionalString($payload, 'valid_to'),
                'maker_change_request_id' => $changeRequestId,
                'sessions_revoked' => $revoked,
            ],
        ));

        return ['role_assignment_id' => $assignment->id];
    }

    public function excludedCheckers(array $payload): array
    {
        return [Payload::string($payload, 'user_id')];
    }

    private function assertScopeReferencesExist(Scope $scope): void
    {
        foreach ($scope->legalEntityIds as $id) {
            if (! $this->db->table('legal_entities')->where('id', $id)->exists()) {
                throw ValidationFailed::with(['scope.legal_entity_ids' => "Legal entity {$id} does not exist."]);
            }
        }
        if ($scope->orgUnitId !== null) {
            $ou = $this->db->table('org_units')->where('id', $scope->orgUnitId)->first();
            if ($ou === null) {
                throw ValidationFailed::with(['scope.org_unit_id' => 'Org unit does not exist.']);
            }
            if ($scope->legalEntityIds !== [] && ! in_array($ou->legal_entity_id, $scope->legalEntityIds, true)) {
                throw ValidationFailed::with(['scope.org_unit_id' => 'Org unit is outside the scoped legal entities.']);
            }
        }
    }
}

<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Application\Actions;

use Fundly\Modules\Access\Application\SessionRevoker;
use Fundly\Modules\Access\Application\SodChecker;
use Fundly\Modules\Access\Contracts\ChangeAction;
use Fundly\Modules\Access\Contracts\SodConflict;
use Fundly\Modules\Access\Domain\Permission;
use Fundly\Modules\Access\Infrastructure\Models\Role;
use Fundly\Modules\Access\Infrastructure\Models\RolePermission;
use Fundly\Modules\Access\Infrastructure\Persistence\GrantRepository;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\Payload;
use Fundly\Shared\Exceptions\NotFound;
use Fundly\Shared\Exceptions\ValidationFailed;
use Fundly\Shared\Json\CanonicalJson;
use Fundly\Shared\Security\Principal;
use Fundly\Shared\Security\ResourceRef;

/** Replace a role's permission bundle (maker-checker; holders are forced to re-authenticate). */
final class SetRolePermissionsAction implements ChangeAction
{
    public const TYPE = 'access.role.set_permissions';

    public function __construct(
        private readonly SodChecker $sod,
        private readonly SessionRevoker $sessions,
        private readonly GrantRepository $grants,
    ) {}

    public function type(): string
    {
        return self::TYPE;
    }

    public function makerPermission(): string
    {
        return Permission::RoleManage->value;
    }

    public function checkerPermission(): string
    {
        return Permission::RoleApprove->value;
    }

    public function entity(array $payload): ResourceRef
    {
        return new ResourceRef('role', Payload::string($payload, 'role_id'));
    }

    public function validate(array $payload, Principal $maker): void
    {
        $role = Role::query()->find(Payload::optionalString($payload, 'role_id'));
        if (! $role instanceof Role) {
            throw new NotFound('Role not found.');
        }
        $perms = Payload::strings($payload, 'permissions');
        $unknown = array_values(array_diff($perms, Permission::codes()));
        if ($unknown !== []) {
            throw ValidationFailed::with(['permissions' => 'Unknown permissions: '.implode(', ', $unknown)]);
        }
        $internal = $this->sod->internalConflicts($perms);
        if ($internal !== []) {
            throw new SodConflict($internal, 'The role would bundle mutually exclusive permissions.');
        }
        $conflicts = [];
        foreach ($this->sod->holdersOf($role->id) as $userId) {
            array_push($conflicts, ...$this->sod->conflictsFor($userId, [], [], $role->id, $perms));
        }
        if ($conflicts !== []) {
            throw new SodConflict($conflicts, 'Current holders of this role would end up with conflicting access.');
        }
    }

    public function fingerprint(array $payload): string
    {
        $role = Role::query()->find(Payload::string($payload, 'role_id'));

        return CanonicalJson::hash([
            'permissions' => $role?->permissionCodes(),
            'holders' => $role === null ? [] : $this->sod->holdersOf($role->id),
        ]);
    }

    public function execute(array $payload, string $changeRequestId, CommandContext $context): array
    {
        $role = Role::query()->findOrFail(Payload::string($payload, 'role_id'));
        $before = $role->permissionCodes();
        $after = Payload::strings($payload, 'permissions');

        RolePermission::query()->where('role_id', $role->id)->delete();
        foreach ($after as $code) {
            (new RolePermission)->forceFill(['role_id' => $role->id, 'permission_code' => $code])->save();
        }
        $role->touch();

        $holders = $this->sod->holdersOf($role->id);
        $this->sessions->revokeMany($holders);
        $this->grants->forget();

        $context->audit(new AuditEntry(
            action: 'access.role.permissions_changed',
            entityType: 'role',
            entityId: $role->id,
            before: ['permissions' => $before],
            after: ['permissions' => $after, 'change_request_id' => $changeRequestId, 'sessions_revoked_for' => $holders],
        ));

        return ['role_id' => $role->id, 'added' => array_values(array_diff($after, $before)), 'removed' => array_values(array_diff($before, $after))];
    }

    public function excludedCheckers(array $payload): array
    {
        // Holders of the role may not approve a change to their own access.
        return $this->sod->holdersOf(Payload::string($payload, 'role_id'));
    }
}

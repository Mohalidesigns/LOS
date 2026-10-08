<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Application\Commands\Roles;

use Fundly\Modules\Access\Application\Support\RolePresenter;
use Fundly\Modules\Access\Infrastructure\Models\Role;
use Fundly\Modules\Access\Infrastructure\Models\RolePermission;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Exceptions\CodedConflict;

final class CloneRoleHandler implements CommandHandler
{
    /** @return array{data: array<string, mixed>, etag?: string} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof CloneRole);
        $source = Role::query()->findOrFail($command->sourceRoleId);
        if (Role::query()->where('code', $command->code)->exists()) {
            throw new CodedConflict('role-code-taken', "A role with code {$command->code} already exists.");
        }
        $role = new Role;
        $role->forceFill([
            'code' => $command->code,
            'name' => $command->name,
            'description' => $command->description ?? $source->description,
            'is_template' => false,
            'cloned_from_id' => $source->id,
        ])->save();
        foreach ($source->permissionCodes() as $code) {
            (new RolePermission)->forceFill(['role_id' => $role->id, 'permission_code' => $code])->save();
        }
        $context->audit(new AuditEntry(action: $command->action(), entityType: 'role', entityId: $role->id, after: RolePresenter::present($role) + ['source_role_id' => $source->id]));

        return ['data' => RolePresenter::present($role), 'etag' => RolePresenter::etag($role)];
    }
}

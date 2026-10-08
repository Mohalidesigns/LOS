<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Application\Commands\Roles;

use Fundly\Modules\Access\Application\Support\RolePresenter;
use Fundly\Modules\Access\Infrastructure\Models\Role;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Exceptions\CodedConflict;

final class CreateRoleHandler implements CommandHandler
{
    /** @return array{data: array<string, mixed>, etag?: string} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof CreateRole);
        if (Role::query()->where('code', $command->code)->exists()) {
            throw new CodedConflict('role-code-taken', "A role with code {$command->code} already exists.");
        }
        $role = new Role;
        $role->forceFill([
            'code' => $command->code,
            'name' => $command->name,
            'description' => $command->description,
            'is_template' => false,
        ])->save();

        $context->audit(new AuditEntry(action: $command->action(), entityType: 'role', entityId: $role->id, after: RolePresenter::present($role)));

        return ['data' => RolePresenter::present($role), 'etag' => RolePresenter::etag($role)];
    }
}

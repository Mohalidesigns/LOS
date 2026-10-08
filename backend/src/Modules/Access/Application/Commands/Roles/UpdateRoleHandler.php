<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Application\Commands\Roles;

use Fundly\Modules\Access\Application\Support\RolePresenter;
use Fundly\Modules\Access\Infrastructure\Models\Role;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Http\ETag;

final class UpdateRoleHandler implements CommandHandler
{
    /** @return array{data: array<string, mixed>, etag?: string} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof UpdateRole);
        $role = Role::query()->lockForUpdate()->findOrFail($command->roleId);
        ETag::assertHeader($command->ifMatch, RolePresenter::etag($role));
        $before = RolePresenter::present($role);
        if ($command->name !== null) {
            $role->name = $command->name;
        }
        if ($command->descriptionProvided) {
            $role->description = $command->description;
        }
        $role->save();
        $context->audit(new AuditEntry(action: $command->action(), entityType: 'role', entityId: $role->id, before: $before, after: RolePresenter::present($role)));

        return ['data' => RolePresenter::present($role), 'etag' => RolePresenter::etag($role)];
    }
}

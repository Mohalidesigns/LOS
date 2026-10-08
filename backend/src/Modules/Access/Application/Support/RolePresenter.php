<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Application\Support;

use Fundly\Modules\Access\Infrastructure\Models\Role;
use Fundly\Shared\Http\ETag;

final class RolePresenter
{
    /** @return array<string, mixed> */
    public static function present(Role $role): array
    {
        return [
            'id' => $role->id,
            'code' => $role->code,
            'name' => $role->name,
            'description' => $role->description,
            'is_template' => $role->is_template,
            'template_key' => $role->template_key,
            'cloned_from_id' => $role->cloned_from_id,
            'permissions' => $role->permissionCodes(),
            'created_at' => $role->created_at->toIso8601ZuluString('microsecond'),
            'updated_at' => $role->updated_at->toIso8601ZuluString('microsecond'),
        ];
    }

    public static function etag(Role $role): string
    {
        return ETag::forRepresentation($role->id, self::present($role));
    }
}

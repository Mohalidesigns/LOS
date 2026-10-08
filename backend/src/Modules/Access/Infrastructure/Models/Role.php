<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Infrastructure\Models;

use Fundly\Shared\Database\TenantModel;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property bool $is_template
 * @property string|null $template_key
 * @property string|null $cloned_from_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class Role extends TenantModel
{
    protected $table = 'roles';

    protected function casts(): array
    {
        return ['is_template' => 'boolean'];
    }

    /** @return list<string> */
    public function permissionCodes(): array
    {
        /** @var list<string> $codes */
        $codes = RolePermission::query()->where('role_id', $this->id)->orderBy('permission_code')->pluck('permission_code')->all();

        return $codes;
    }
}

<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Infrastructure\Models;

use Fundly\Shared\Database\TenantScope;
use Fundly\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $tenant_id
 * @property string $role_id
 * @property string $permission_code
 */
final class RolePermission extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $table = 'role_permissions';

    protected $primaryKey = 'role_id';

    protected $guarded = [];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
        static::creating(static function (RolePermission $m): void {
            $m->setAttribute('tenant_id', app(TenantContext::class)->requireId());
        });
    }
}

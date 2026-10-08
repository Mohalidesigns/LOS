<?php

declare(strict_types=1);

namespace Fundly\Shared\Database;

use Fundly\Shared\Tenancy\TenantContext;

/**
 * A tenant-owned row: tenant_id is always taken from the tenant context
 * (never from input) and every query is tenant-filtered.
 *
 * @property string $tenant_id
 */
abstract class TenantModel extends Model
{
    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
        static::creating(static function (TenantModel $model): void {
            $model->setAttribute('tenant_id', app(TenantContext::class)->requireId());
        });
    }
}

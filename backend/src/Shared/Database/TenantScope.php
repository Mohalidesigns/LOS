<?php

declare(strict_types=1);

namespace Fundly\Shared\Database;

use Fundly\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Scope;

/**
 * Application-layer tenant filter (defence in depth on top of RLS, TRD §2.3).
 * Without a tenant context, tenant-owned queries return nothing.
 */
final class TenantScope implements Scope
{
    /** @param Builder<EloquentModel> $builder */
    public function apply(Builder $builder, EloquentModel $model): void
    {
        $tenant = app(TenantContext::class)->id();
        if ($tenant === null) {
            $builder->whereRaw('false');

            return;
        }
        $builder->where($model->qualifyColumn('tenant_id'), $tenant);
    }
}

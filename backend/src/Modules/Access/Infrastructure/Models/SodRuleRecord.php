<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Infrastructure\Models;

use Fundly\Shared\Database\TenantModel;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $kind
 * @property string $left_ref
 * @property string $right_ref
 * @property string $description
 * @property bool $enabled
 * @property string|null $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class SodRuleRecord extends TenantModel
{
    protected $table = 'sod_rules';

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }
}

<?php

declare(strict_types=1);

namespace Fundly\Modules\Platform\Infrastructure\Models;

use Fundly\Shared\Database\TenantModel;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $legal_entity_id
 * @property string|null $parent_id
 * @property string $code
 * @property string $name
 * @property int $depth
 * @property string $status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class OrgUnit extends TenantModel
{
    protected $table = 'org_units';

    protected function casts(): array
    {
        return ['depth' => 'integer'];
    }
}

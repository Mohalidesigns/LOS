<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Infrastructure\Models;

use Fundly\Shared\Database\TenantModel;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $delegator_id
 * @property string $delegate_id
 * @property string $role_assignment_id
 * @property string $reason
 * @property Carbon $valid_from
 * @property Carbon $valid_to
 * @property Carbon|null $revoked_at
 * @property string|null $revoked_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class Delegation extends TenantModel
{
    protected $table = 'delegations';

    protected function casts(): array
    {
        return ['valid_from' => 'datetime', 'valid_to' => 'datetime', 'revoked_at' => 'datetime'];
    }
}

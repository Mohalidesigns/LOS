<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Infrastructure\Models;

use Fundly\Shared\Database\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $user_id
 * @property string $role_id
 * @property array<string, mixed> $scope
 * @property Carbon $valid_from
 * @property Carbon|null $valid_to
 * @property string $granted_by
 * @property string|null $change_request_id
 * @property Carbon|null $revoked_at
 * @property string|null $revoked_by
 * @property string|null $revoke_change_request_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class RoleAssignment extends TenantModel
{
    protected $table = 'role_assignments';

    protected function casts(): array
    {
        return [
            'scope' => 'array',
            'valid_from' => 'datetime',
            'valid_to' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Role, $this> */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

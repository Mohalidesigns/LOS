<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Infrastructure\Models;

use Fundly\Shared\Database\TenantModel;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 * Local identity (LOS-FR-302). Humans authenticate with password + TOTP;
 * service accounts authenticate only with scoped tokens (FR-SEC-014).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $kind
 * @property string $email
 * @property string $name
 * @property string|null $password
 * @property string $status
 * @property string|null $home_legal_entity_id
 * @property string|null $home_org_unit_id
 * @property string|null $mfa_secret
 * @property Carbon|null $mfa_confirmed_at
 * @property int|null $mfa_last_used_step
 * @property int $failed_login_count
 * @property Carbon|null $locked_until
 * @property Carbon|null $last_login_at
 * @property Carbon|null $password_changed_at
 * @property int $auth_version
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class User extends TenantModel implements AuthenticatableContract
{
    use Authenticatable;

    /** @use HasApiTokens<PersonalAccessToken> */
    use HasApiTokens;

    public const KIND_HUMAN = 'human';

    public const KIND_SERVICE = 'service';

    protected $table = 'users';

    protected $hidden = ['password', 'mfa_secret', 'remember_token'];

    /** Remember-me is disabled (session controls, FR-SEC-015). */
    public function getRememberTokenName(): string
    {
        return '';
    }

    protected function casts(): array
    {
        return [
            'mfa_confirmed_at' => 'datetime',
            'locked_until' => 'datetime',
            'last_login_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'mfa_last_used_step' => 'integer',
            'failed_login_count' => 'integer',
            'auth_version' => 'integer',
        ];
    }

    /** @return HasMany<RoleAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(RoleAssignment::class, 'user_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isService(): bool
    {
        return $this->kind === self::KIND_SERVICE;
    }
}

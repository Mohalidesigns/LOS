<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Infrastructure\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumToken;

/**
 * Sanctum token with the owning tenant recorded, so the tenant context can be
 * established from the credential before the user row (RLS-protected) is read.
 *
 * @property string $tenant_id
 */
final class PersonalAccessToken extends SanctumToken
{
    protected $dateFormat = 'Y-m-d H:i:s.uP';

    protected static function booted(): void
    {
        self::creating(static function (PersonalAccessToken $token): void {
            $owner = $token->tokenable;
            if ($owner instanceof User) {
                $token->setAttribute('tenant_id', $owner->tenant_id);
            }
        });
    }
}

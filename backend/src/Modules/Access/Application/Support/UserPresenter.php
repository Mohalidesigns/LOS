<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Application\Support;

use Fundly\Modules\Access\Infrastructure\Models\User;
use Fundly\Shared\Http\ETag;

final class UserPresenter
{
    /** @return array<string, mixed> */
    public static function present(User $u): array
    {
        return [
            'id' => $u->id,
            'kind' => $u->kind,
            'email' => $u->email,
            'name' => $u->name,
            'status' => $u->status,
            'home_legal_entity_id' => $u->home_legal_entity_id,
            'home_org_unit_id' => $u->home_org_unit_id,
            'mfa_enrolled' => $u->mfa_confirmed_at !== null,
            'last_login_at' => $u->last_login_at?->toIso8601ZuluString('microsecond'),
            'created_at' => $u->created_at->toIso8601ZuluString('microsecond'),
            'updated_at' => $u->updated_at->toIso8601ZuluString('microsecond'),
        ];
    }

    public static function etag(User $u): string
    {
        return ETag::of($u->id, $u->updated_at->format('Uu'));
    }
}

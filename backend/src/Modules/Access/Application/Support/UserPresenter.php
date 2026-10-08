<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Application\Support;

use Fundly\Modules\Access\Infrastructure\Models\User;
use Fundly\Shared\Http\ETag;
use Fundly\Shared\Pii\PiiMasker;

final class UserPresenter
{
    /**
     * Field-level control (FR-SEC-001): the email address is a sensitive
     * attribute, returned in clear only to principals allowed to see it.
     *
     * @return array<string, mixed>
     */
    public static function present(User $u, bool $revealSensitive = true): array
    {
        return [
            'id' => $u->id,
            'kind' => $u->kind,
            'email' => $revealSensitive ? $u->email : PiiMasker::maskValue($u->email),
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

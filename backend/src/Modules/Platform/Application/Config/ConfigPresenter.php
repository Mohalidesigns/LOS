<?php

declare(strict_types=1);

namespace Fundly\Modules\Platform\Application\Config;

use Fundly\Modules\Platform\Infrastructure\Models\ConfigArtifact;
use Fundly\Modules\Platform\Infrastructure\Models\ConfigVersion;
use Fundly\Shared\Http\ETag;

final class ConfigPresenter
{
    /** @return array<string, mixed> */
    public static function artifact(ConfigArtifact $a): array
    {
        return [
            'id' => $a->id,
            'type' => $a->type,
            'key' => $a->key,
            'name' => $a->name,
            'description' => $a->description,
            'active_version_id' => $a->active_version_id,
            'created_at' => $a->created_at->toIso8601ZuluString('microsecond'),
            'updated_at' => $a->updated_at->toIso8601ZuluString('microsecond'),
        ];
    }

    /** @return array<string, mixed> */
    public static function version(ConfigVersion $v): array
    {
        return [
            'id' => $v->id,
            'artifact_id' => $v->artifact_id,
            'version_no' => $v->version_no,
            'status' => $v->status,
            'content' => $v->content,
            'content_hash' => $v->content_hash,
            'notes' => $v->notes,
            'created_by' => $v->created_by,
            'submitted_by' => $v->submitted_by,
            'submitted_at' => $v->submitted_at?->toIso8601ZuluString('microsecond'),
            'reviewed_by' => $v->reviewed_by,
            'reviewed_at' => $v->reviewed_at?->toIso8601ZuluString('microsecond'),
            'review_reason' => $v->review_reason,
            'activated_by' => $v->activated_by,
            'activated_at' => $v->activated_at?->toIso8601ZuluString('microsecond'),
            'activation_change_request_id' => $v->activation_change_request_id,
            'superseded_at' => $v->superseded_at?->toIso8601ZuluString('microsecond'),
            'created_at' => $v->created_at->toIso8601ZuluString('microsecond'),
            'updated_at' => $v->updated_at->toIso8601ZuluString('microsecond'),
        ];
    }

    public static function etag(ConfigVersion $v): string
    {
        return ETag::forRepresentation($v->id, self::version($v));
    }
}

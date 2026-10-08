<?php

declare(strict_types=1);

namespace Fundly\Modules\Platform\Application\Support;

use Fundly\Modules\Platform\Infrastructure\Models\LegalEntity;
use Fundly\Modules\Platform\Infrastructure\Models\OrgUnit;
use Fundly\Shared\Http\ETag;

final class PlatformPresenter
{
    /** @return array<string, mixed> */
    public static function legalEntity(LegalEntity $le): array
    {
        return [
            'id' => $le->id,
            'code' => $le->code,
            'name' => $le->name,
            'jurisdiction' => $le->jurisdiction,
            'licence_category' => $le->licence_category,
            'base_currency' => $le->base_currency,
            'timezone' => $le->timezone,
            'org_level_labels' => $le->org_level_labels,
            'status' => $le->status,
            'created_at' => $le->created_at->toIso8601ZuluString('microsecond'),
            'updated_at' => $le->updated_at->toIso8601ZuluString('microsecond'),
        ];
    }

    /** @return array<string, mixed> */
    public static function orgUnit(OrgUnit $ou, ?string $levelLabel = null): array
    {
        return [
            'id' => $ou->id,
            'legal_entity_id' => $ou->legal_entity_id,
            'parent_id' => $ou->parent_id,
            'code' => $ou->code,
            'name' => $ou->name,
            'depth' => $ou->depth,
            'level_label' => $levelLabel,
            'status' => $ou->status,
            'created_at' => $ou->created_at->toIso8601ZuluString('microsecond'),
            'updated_at' => $ou->updated_at->toIso8601ZuluString('microsecond'),
        ];
    }

    public static function etag(LegalEntity|OrgUnit $m): string
    {
        return ETag::forRepresentation($m->id, $m instanceof LegalEntity ? self::legalEntity($m) : self::orgUnit($m));
    }

    public static function levelLabel(LegalEntity $le, int $depth): ?string
    {
        $labels = $le->org_level_labels;

        return $labels[$depth] ?? null;
    }
}

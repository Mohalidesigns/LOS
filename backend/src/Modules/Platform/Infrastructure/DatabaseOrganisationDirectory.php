<?php

declare(strict_types=1);

namespace Fundly\Modules\Platform\Infrastructure;

use Fundly\Modules\Platform\Contracts\LegalEntityView;
use Fundly\Modules\Platform\Contracts\OrganisationDirectory;
use Fundly\Modules\Platform\Infrastructure\Models\LegalEntity;
use Fundly\Modules\Platform\Infrastructure\Models\OrgUnit;
use Illuminate\Support\Facades\DB;

final class DatabaseOrganisationDirectory implements OrganisationDirectory
{
    public function legalEntity(string $id): ?LegalEntityView
    {
        if (preg_match('/^[0-9a-f-]{36}$/', $id) !== 1) {
            return null;
        }
        $le = LegalEntity::query()->find($id);

        return $le === null ? null : new LegalEntityView(
            id: $le->id,
            code: (string) $le->code,
            name: (string) $le->name,
            jurisdiction: (string) $le->jurisdiction,
            licenceCategory: (string) $le->licence_category,
            baseCurrency: (string) $le->base_currency,
            timezone: (string) $le->timezone,
        );
    }

    public function orgUnitBelongsTo(string $orgUnitId, string $legalEntityId): bool
    {
        if (preg_match('/^[0-9a-f-]{36}$/', $orgUnitId) !== 1) {
            return false;
        }

        return OrgUnit::query()->whereKey($orgUnitId)->where('legal_entity_id', $legalEntityId)->where('status', 'active')->exists();
    }

    public function subtree(string $orgUnitId): array
    {
        /** @var list<string> */
        return DB::table('org_unit_closure')->where('ancestor_id', $orgUnitId)->pluck('descendant_id')->map(static fn ($v): string => (string) $v)->all();
    }
}

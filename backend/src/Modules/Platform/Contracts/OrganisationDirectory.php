<?php

declare(strict_types=1);

namespace Fundly\Modules\Platform\Contracts;

/** Read-only view of the tenant's legal entities and org hierarchy for other modules. */
interface OrganisationDirectory
{
    public function legalEntity(string $id): ?LegalEntityView;

    /** True when the org unit exists, is active and belongs to the legal entity. */
    public function orgUnitBelongsTo(string $orgUnitId, string $legalEntityId): bool;

    /** @return list<string> the org unit and all of its descendants */
    public function subtree(string $orgUnitId): array;
}

<?php

declare(strict_types=1);

namespace Fundly\Modules\Platform\Application\Queries;

use Fundly\Modules\Platform\Application\Config\ConfigPresenter;
use Fundly\Modules\Platform\Application\Config\ConfigTypeRegistry;
use Fundly\Modules\Platform\Application\Support\PlatformPresenter;
use Fundly\Modules\Platform\Infrastructure\Models\ConfigArtifact;
use Fundly\Modules\Platform\Infrastructure\Models\ConfigVersion;
use Fundly\Modules\Platform\Infrastructure\Models\LegalEntity;
use Fundly\Modules\Platform\Infrastructure\Models\OrgUnit;
use Fundly\Shared\Exceptions\NotFound;
use Fundly\Shared\Http\CursorPaginator;
use Fundly\Shared\Security\AuthorizationGate;
use Fundly\Shared\Security\ListScopeFilter;
use Fundly\Shared\Security\Principal;
use Fundly\Shared\Security\ResourceAttributes;
use Fundly\Shared\Security\ScopeColumns;
use Illuminate\Http\Request;

final class PlatformQueries
{
    public function __construct(
        private readonly ListScopeFilter $scope,
        private readonly AuthorizationGate $gate,
        private readonly ConfigTypeRegistry $types,
    ) {}

    /** @return array<string, mixed> */
    public function legalEntities(Request $request, Principal $principal): array
    {
        $q = LegalEntity::query();
        $this->scope->apply($q, $principal, 'legal_entity:read', new ScopeColumns(legalEntity: 'id'));

        return CursorPaginator::paginate($q, $request, static fn (LegalEntity $le): array => PlatformPresenter::legalEntity($le));
    }

    /** @return array{data: array<string, mixed>, etag: string} */
    public function legalEntity(string $id, Principal $principal): array
    {
        $le = LegalEntity::query()->find($id) ?? throw new NotFound('Legal entity not found.');
        $this->gate->authorize($principal, 'legal_entity:read', new ResourceAttributes(legalEntityId: $le->id, entityType: 'legal_entity', entityId: $le->id));

        return ['data' => PlatformPresenter::legalEntity($le), 'etag' => PlatformPresenter::etag($le)];
    }

    /** @return array<string, mixed> */
    public function orgUnits(Request $request, Principal $principal): array
    {
        $q = OrgUnit::query();
        $this->scope->apply($q, $principal, 'org_unit:read', new ScopeColumns(legalEntity: 'legal_entity_id', orgUnit: 'id'));
        $filter = $request->query('filter');
        $filter = is_array($filter) ? $filter : [];
        if (isset($filter['legal_entity_id']) && is_string($filter['legal_entity_id'])) {
            $q->where('legal_entity_id', $filter['legal_entity_id']);
        }
        if (isset($filter['parent_id']) && is_string($filter['parent_id'])) {
            $filter['parent_id'] === 'null' ? $q->whereNull('parent_id') : $q->where('parent_id', $filter['parent_id']);
        }
        $labels = LegalEntity::query()->pluck('org_level_labels', 'id')->all();

        return CursorPaginator::paginate($q, $request, static function (OrgUnit $ou) use ($labels): array {
            $l = $labels[$ou->legal_entity_id] ?? [];

            return PlatformPresenter::orgUnit($ou, is_array($l) ? ($l[$ou->depth] ?? null) : null);
        });
    }

    /** @return array{data: array<string, mixed>, etag: string} */
    public function orgUnit(string $id, Principal $principal): array
    {
        $ou = OrgUnit::query()->find($id) ?? throw new NotFound('Org unit not found.');
        $this->gate->authorize($principal, 'org_unit:read', new ResourceAttributes(legalEntityId: $ou->legal_entity_id, orgUnitId: $ou->id, entityType: 'org_unit', entityId: $ou->id));
        $le = LegalEntity::query()->findOrFail($ou->legal_entity_id);

        return ['data' => PlatformPresenter::orgUnit($ou, PlatformPresenter::levelLabel($le, $ou->depth)), 'etag' => PlatformPresenter::etag($ou)];
    }

    /** @return array<string, mixed> */
    public function configArtifacts(string $type, Request $request): array
    {
        $this->assertType($type);

        return CursorPaginator::paginate(ConfigArtifact::query()->where('type', $type), $request, static fn (ConfigArtifact $a): array => ConfigPresenter::artifact($a));
    }

    /** @return array<string, mixed> */
    public function configArtifact(string $type, string $id): array
    {
        return ConfigPresenter::artifact($this->artifact($type, $id));
    }

    /** @return array<string, mixed> */
    public function configVersions(string $type, string $artifactId, Request $request): array
    {
        $a = $this->artifact($type, $artifactId);

        return CursorPaginator::paginate(ConfigVersion::query()->where('artifact_id', $a->id), $request, static fn (ConfigVersion $v): array => ConfigPresenter::version($v), 'version_no');
    }

    /** @return array{data: array<string, mixed>, etag: string} */
    public function configVersion(string $type, string $artifactId, string $versionId): array
    {
        $a = $this->artifact($type, $artifactId);
        $v = ConfigVersion::query()->where('artifact_id', $a->id)->find($versionId) ?? throw new NotFound('Configuration version not found.');

        return ['data' => ConfigPresenter::version($v), 'etag' => ConfigPresenter::etag($v)];
    }

    private function artifact(string $type, string $id): ConfigArtifact
    {
        $this->assertType($type);

        return ConfigArtifact::query()->where('type', $type)->find($id) ?? throw new NotFound('Configuration artefact not found.');
    }

    private function assertType(string $type): void
    {
        if (! $this->types->has($type)) {
            throw new NotFound("Unknown configuration type {$type}.");
        }
    }
}

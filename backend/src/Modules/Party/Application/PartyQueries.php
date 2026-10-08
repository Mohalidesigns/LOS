<?php

declare(strict_types=1);

namespace Fundly\Modules\Party\Application;

use Fundly\Modules\Party\Domain\NameNormaliser;
use Fundly\Modules\Party\Domain\OwnershipCalculator;
use Fundly\Modules\Party\Infrastructure\Models\Party;
use Fundly\Modules\Party\Infrastructure\Models\PartyIdentity;
use Fundly\Modules\Party\Infrastructure\Models\PartyRelationship;
use Fundly\Shared\Exceptions\NotFound;
use Fundly\Shared\Http\CursorPaginator;
use Fundly\Shared\Security\AuthorizationGate;
use Fundly\Shared\Security\ListScopeFilter;
use Fundly\Shared\Security\Principal;
use Fundly\Shared\Security\ResourceAttributes;
use Fundly\Shared\Security\ScopeColumns;
use Illuminate\Http\Request;

final class PartyQueries
{
    public const READ = 'application:view';

    public function __construct(
        private readonly ListScopeFilter $scope,
        private readonly AuthorizationGate $gate,
        private readonly PartyPresenter $presenter,
        private readonly PartyMatcher $matcher,
    ) {}

    /** @return array<string, mixed> */
    public function list(Request $request, Principal $principal): array
    {
        $q = Party::query();
        $this->scope->apply($q, $principal, self::READ, new ScopeColumns(orgUnit: 'org_unit_id'));
        $filter = $request->query('filter');
        $filter = is_array($filter) ? $filter : [];
        if (isset($filter['type']) && is_string($filter['type'])) {
            $q->where('type', $filter['type']);
        }
        if (isset($filter['q']) && is_string($filter['q']) && trim($filter['q']) !== '') {
            $needle = NameNormaliser::normalise($filter['q']);
            $q->where(static function ($w) use ($needle, $filter): void {
                $w->whereRaw("name_normalised like '%' || ? || '%'", [$needle])
                    ->orWhereRaw('similarity(name_normalised, ?) >= 0.4', [$needle])
                    ->orWhere('registration_number', strtoupper(trim((string) $filter['q'])));
            });
        }

        return CursorPaginator::paginate($q, $request, $this->presenter->summary(...));
    }

    /** @return array{data: array<string, mixed>, etag: string} */
    public function show(string $id, Principal $principal): array
    {
        $party = $this->authorized($id, $principal);

        return ['data' => $this->presenter->detail($party, PartyIdentity::query()->where('party_id', $party->id)->orderBy('type')->get()), 'etag' => PartyPresenter::etag($party)];
    }

    /**
     * Directors, shareholders, signatories and the look-through beneficial owners (FR-CUS-006).
     *
     * @return array{data: array{relationships: list<array<string, mixed>>, beneficial_owners: list<array<string, mixed>>}}
     */
    public function relationships(string $id, Principal $principal): array
    {
        $party = $this->authorized($id, $principal);
        $rels = PartyRelationship::query()->where('party_id', $party->id)->orderBy('created_at')->get();
        $related = Party::query()->whereIn('id', $rels->pluck('related_party_id')->all())->get()->keyBy('id');
        $out = [];
        foreach ($rels as $rel) {
            $p = $related[$rel->related_party_id] ?? null;
            if ($p !== null) {
                $out[] = self::relationship($rel, $p);
            }
        }

        return ['data' => ['relationships' => $out, 'beneficial_owners' => $this->beneficialOwners($party)]];
    }

    /**
     * @param  array<string, mixed>  $probe
     * @return array{data: list<array<string, mixed>>}
     */
    public function match(array $probe): array
    {
        /** @var array{name?: ?string, phone?: ?string, email?: ?string, tin?: ?string, registration_number?: ?string, identities?: list<array{type: string, value: string}>} $probe */
        return ['data' => $this->matcher->match($probe)];
    }

    /** @return array<string, mixed> */
    public static function relationship(PartyRelationship $rel, Party $related): array
    {
        return [
            'id' => $rel->id,
            'role' => $rel->role,
            'ownership_percent' => $rel->ownership_percent,
            'notes' => $rel->notes,
            'party' => ['id' => $related->id, 'type' => $related->type, 'display_name' => $related->display_name],
            'created_at' => $rel->created_at->toIso8601ZuluString('microsecond'),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function beneficialOwners(Party $company): array
    {
        if ($company->type !== 'limited_company') {
            return [];
        }
        // Walk shareholder edges breadth-first (bounded) to build the ownership graph.
        $edges = [];
        $types = [$company->id => $company->type];
        $declared = [];
        $frontier = [$company->id];
        for ($depth = 0; $depth < 10 && $frontier !== []; $depth++) {
            $rows = PartyRelationship::query()->whereIn('party_id', $frontier)->whereIn('role', ['shareholder', 'beneficial_owner'])->get();
            $next = [];
            foreach ($rows as $r) {
                if ($r->ownership_percent === null) {
                    continue;
                }
                if ($r->role === 'beneficial_owner') {
                    if ($r->party_id === $company->id) {
                        $declared[$r->related_party_id] = (string) $r->ownership_percent;
                    }

                    continue;
                }
                $edges[$r->party_id][] = ['owner' => $r->related_party_id, 'percent' => (string) $r->ownership_percent];
                if (! isset($types[$r->related_party_id])) {
                    $next[] = $r->related_party_id;
                }
            }
            foreach (Party::query()->whereIn('id', $next)->get(['id', 'type']) as $p) {
                $types[$p->id] = $p->type;
            }
            $frontier = array_values(array_filter($next, static fn (string $id): bool => ($types[$id] ?? null) === 'limited_company'));
        }
        $effective = OwnershipCalculator::effective($company->id, $edges, $types);
        foreach ($declared as $id => $pct) {
            $effective[$id] ??= $pct;
        }
        $names = Party::query()->whereIn('id', array_keys($effective))->pluck('display_name', 'id');
        $out = [];
        foreach ($effective as $id => $pct) {
            $out[] = ['party_id' => (string) $id, 'display_name' => (string) ($names[$id] ?? ''), 'effective_percent' => $pct, 'source' => isset($declared[$id]) && ! isset($edges[$company->id]) ? 'declared' : 'look_through'];
        }

        return $out;
    }

    private function authorized(string $id, Principal $principal): Party
    {
        $party = preg_match('/^[0-9a-f-]{36}$/', $id) === 1 ? Party::query()->find($id) : null;
        if ($party === null) {
            throw new NotFound('Party not found.');
        }
        $this->gate->authorize($principal, self::READ, new ResourceAttributes(orgUnitId: $party->org_unit_id, entityType: 'party', entityId: $party->id));

        return $party;
    }
}

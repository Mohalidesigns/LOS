<?php

declare(strict_types=1);

namespace Fundly\Modules\Party\Infrastructure;

use Fundly\Modules\Party\Contracts\PartyDirectory;
use Fundly\Modules\Party\Contracts\PartySummary;
use Fundly\Modules\Party\Infrastructure\Models\Party;

final class EloquentPartyDirectory implements PartyDirectory
{
    public function find(string $partyId): ?PartySummary
    {
        return $this->findMany([$partyId])[$partyId] ?? null;
    }

    public function findMany(array $partyIds): array
    {
        $ids = array_values(array_filter($partyIds, static fn (string $id): bool => preg_match('/^[0-9a-f-]{36}$/', $id) === 1));
        if ($ids === []) {
            return [];
        }
        $out = [];
        foreach (Party::query()->whereIn('id', $ids)->get() as $p) {
            $out[$p->id] = new PartySummary($p->id, $p->type, $p->display_name, $p->org_unit_id, $p->status);
        }

        return $out;
    }
}

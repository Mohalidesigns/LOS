<?php

declare(strict_types=1);

namespace Fundly\Modules\Party\Application;

use Fundly\Modules\Party\Domain\IdentityType;
use Fundly\Modules\Party\Domain\NameNormaliser;
use Fundly\Shared\Crypto\FieldEncryptor;
use Illuminate\Support\Facades\DB;

/**
 * Intake deduplication (FR-CHN-007): exact matches on encrypted identifiers
 * via blind index, plus trigram similarity on the normalised name. Returns
 * candidates with the fields that matched; the caller decides block vs flag.
 */
final class PartyMatcher
{
    public const NAME_THRESHOLD = '0.55';

    public function __construct(private readonly FieldEncryptor $crypto) {}

    /**
     * @param  array{name?: ?string, phone?: ?string, email?: ?string, tin?: ?string, registration_number?: ?string, identities?: list<array{type: string, value: string}>}  $probe
     * @return list<array{party_id: string, display_name: string, type: string, matched_fields: list<string>, name_similarity: ?string}>
     */
    public function match(array $probe, ?string $excludePartyId = null): array
    {
        /** @var array<string, array{fields: array<string, true>, sim: ?string}> $hits */
        $hits = [];
        $add = static function (iterable $ids, string $field) use (&$hits): void {
            foreach ($ids as $id) {
                $hits[(string) $id]['fields'][$field] = true;
                $hits[(string) $id]['sim'] ??= null;
            }
        };

        foreach ($probe['identities'] ?? [] as $identity) {
            $type = IdentityType::tryFrom($identity['type']);
            if ($type === null || $identity['value'] === '') {
                continue;
            }
            $add(DB::table('party_identities')->where('type', $type->value)->where('value_bidx', $this->crypto->blindIndex($identity['value'], 'party_identity.'.$type->value))->pluck('party_id'), $type->value);
        }
        foreach (['phone' => 'party.phone', 'email' => 'party.email', 'tin' => 'party.tin'] as $key => $field) {
            $value = $probe[$key] ?? null;
            if (is_string($value) && $value !== '') {
                $add(DB::table('parties')->where($key.'_bidx', $this->crypto->blindIndex($value, $field))->pluck('id'), $key);
            }
        }
        $rc = $probe['registration_number'] ?? null;
        if (is_string($rc) && $rc !== '') {
            $add(DB::table('parties')->where('registration_number', strtoupper(trim($rc)))->pluck('id'), 'registration_number');
        }
        $name = $probe['name'] ?? null;
        if (is_string($name) && trim($name) !== '') {
            $rows = DB::table('parties')
                ->selectRaw('id, similarity(name_normalised, ?)::numeric(4,3)::text as sim', [NameNormaliser::normalise($name)])
                ->whereRaw('similarity(name_normalised, ?) >= ?', [NameNormaliser::normalise($name), self::NAME_THRESHOLD])
                ->limit(20)->get();
            foreach ($rows as $row) {
                $hits[(string) $row->id]['fields']['name'] = true;
                $hits[(string) $row->id]['sim'] = (string) $row->sim;
            }
        }
        unset($hits[(string) $excludePartyId]);
        if ($hits === []) {
            return [];
        }

        $parties = DB::table('parties')->whereIn('id', array_keys($hits))->get(['id', 'display_name', 'type'])->keyBy('id');
        $out = [];
        foreach ($hits as $id => $hit) {
            $p = $parties[$id] ?? null;
            if ($p === null) {
                continue;
            }
            $out[] = ['party_id' => $id, 'display_name' => (string) $p->display_name, 'type' => (string) $p->type, 'matched_fields' => array_keys($hit['fields']), 'name_similarity' => $hit['sim']];
        }
        usort($out, static fn (array $a, array $b): int => count($b['matched_fields']) <=> count($a['matched_fields']));

        return $out;
    }
}

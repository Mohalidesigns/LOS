<?php

declare(strict_types=1);

namespace Fundly\Modules\Offer\Domain;

/**
 * Who signs, in which order (FR-OFR-009): the template's role order applied
 * to the application's parties, plus one bank signatory if the template asks
 * for one. A role nobody holds is skipped.
 */
final class SigningOrder
{
    /**
     * @param  list<string>  $roles  template signing order
     * @param  list<array{party_id: string, role: string, name: string}>  $parties  application parties in a stable order
     * @return list<array{position: int, role: string, party_id: ?string, name: string}>
     */
    public static function expected(array $roles, array $parties, string $bankName): array
    {
        $out = [];
        foreach ($roles as $role) {
            if ($role === 'bank') {
                $out[] = ['position' => count($out) + 1, 'role' => 'bank', 'party_id' => null, 'name' => "For and on behalf of {$bankName}"];

                continue;
            }
            foreach ($parties as $p) {
                if ($p['role'] === $role) {
                    $out[] = ['position' => count($out) + 1, 'role' => $role, 'party_id' => $p['party_id'], 'name' => $p['name']];
                }
            }
        }

        return $out;
    }

    /**
     * The first signatory still to sign; signing out of order is refused.
     *
     * @param  list<array{id: string, position: int, signed: bool}>  $signatories
     */
    public static function nextToSign(array $signatories): ?string
    {
        usort($signatories, static fn (array $a, array $b): int => $a['position'] <=> $b['position']);
        foreach ($signatories as $s) {
            if (! $s['signed']) {
                return $s['id'];
            }
        }

        return null;
    }
}

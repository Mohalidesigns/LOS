<?php

declare(strict_types=1);

namespace Fundly\Modules\Conditions\Domain;

/**
 * Destination-account name check (FR-CPR-005). Both names are normalised
 * (case, punctuation, company suffixes, honorifics); the score is the share
 * of the applicant's name tokens found in the account name, 0..100. Word
 * order does not matter, so "ADEBAYO FUNKE" matches "Funke Adebayo".
 */
final class NameMatch
{
    private const NOISE = ['LTD', 'LIMITED', 'PLC', 'NIG', 'NIGERIA', 'ENTERPRISES', 'ENT', 'CO', 'COMPANY', 'MR', 'MRS', 'MS', 'MISS', 'DR', 'CHIEF', 'ALHAJI', 'ALHAJA', 'THE', 'AND', '&'];

    public static function score(string $applicantName, string $accountName): int
    {
        $want = self::tokens($applicantName);
        $have = self::tokens($accountName);
        if ($want === [] || $have === []) {
            return 0;
        }
        $found = count(array_intersect($want, $have));

        return intdiv($found * 100, count($want));
    }

    /** @return list<string> */
    private static function tokens(string $name): array
    {
        $clean = strtoupper((string) preg_replace('/[^A-Za-z0-9 ]+/', ' ', $name));
        $tokens = array_filter(explode(' ', $clean), static fn (string $t): bool => $t !== '' && ! in_array($t, self::NOISE, true));

        return array_values(array_unique($tokens));
    }
}

<?php

declare(strict_types=1);

namespace Fundly\Modules\Party\Domain;

/**
 * Normalises names for fuzzy matching (FR-CHN-007): lower case, ASCII-folded,
 * punctuation and company suffixes removed, single spaces, tokens sorted so
 * "Okafor Chidi" matches "Chidi Okafor".
 */
final class NameNormaliser
{
    private const NOISE = ['ltd', 'limited', 'plc', 'nig', 'nigeria', 'enterprises', 'ventures', 'mr', 'mrs', 'ms', 'dr', 'chief', 'alhaji', 'the'];

    public static function normalise(string $name): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
        $lower = strtolower(is_string($ascii) ? $ascii : $name);
        $clean = preg_replace('/[^a-z0-9 ]+/', ' ', $lower) ?? $lower;
        $tokens = array_values(array_filter(explode(' ', $clean), static fn (string $t): bool => $t !== '' && ! in_array($t, self::NOISE, true)));
        sort($tokens);

        return implode(' ', $tokens);
    }
}

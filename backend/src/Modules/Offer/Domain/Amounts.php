<?php

declare(strict_types=1);

namespace Fundly\Modules\Offer\Domain;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/** Display formatting for documents: exact decimals, grouped thousands, never floats. */
final class Amounts
{
    /** @param int<0, max> $minorUnits */
    public static function money(string $amount, string $currency, int $minorUnits = 2): string
    {
        $d = BigDecimal::of($amount)->toScale($minorUnits, RoundingMode::HalfEven);
        $negative = $d->isNegative();
        [$int, $frac] = array_pad(explode('.', (string) $d->abs()), 2, '');
        $grouped = strrev(implode(',', str_split(strrev($int), 3)));

        return ($negative ? '-' : '').$currency.' '.$grouped.($frac === '' ? '' : '.'.$frac);
    }

    /** @param int<0, max> $scale */
    public static function percent(string $value, int $scale = 2): string
    {
        return (string) BigDecimal::of($value)->toScale($scale, RoundingMode::HalfEven).'%';
    }
}

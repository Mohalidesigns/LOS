<?php

declare(strict_types=1);

namespace Fundly\Modules\Disbursement\Domain;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/** Amounts for documents: exact decimals with grouped thousands. */
final class Display
{
    public static function money(string $amount, string $currency): string
    {
        $d = BigDecimal::of($amount)->toScale(2, RoundingMode::HalfEven);
        [$int, $frac] = explode('.', (string) $d->abs());

        return ($d->isNegative() ? '-' : '').$currency.' '.strrev(implode(',', str_split(strrev($int), 3))).'.'.$frac;
    }
}

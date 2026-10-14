<?php

declare(strict_types=1);

namespace Fundly\Modules\Notification\Domain;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/** Message formatting: exact decimals, masked addresses and numbers. */
final class Format
{
    public static function money(string $amount, string $currency): string
    {
        $d = BigDecimal::of($amount)->toScale(2, RoundingMode::HalfEven);
        [$int, $frac] = explode('.', (string) $d->abs());

        return ($d->isNegative() ? '-' : '').$currency.' '.strrev(implode(',', str_split(strrev($int), 3))).'.'.$frac;
    }

    public static function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return substr($local, 0, 1).str_repeat('*', max(1, strlen($local) - 1)).'@'.$domain;
    }

    public static function maskPhone(string $phone): string
    {
        $digits = (string) preg_replace('/\D+/', '', $phone);

        return '+'.substr($digits, 0, 3).str_repeat('*', max(1, strlen($digits) - 6)).substr($digits, -3);
    }
}

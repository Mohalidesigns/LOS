<?php

declare(strict_types=1);

namespace Fundly\Modules\Party\Domain;

final class PhoneNumber
{
    /** Nigerian numbers to E.164 (+234…); others kept as digits with a leading +. */
    public static function normalise(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            return '+234'.substr($digits, 1);
        }

        return '+'.$digits;
    }
}

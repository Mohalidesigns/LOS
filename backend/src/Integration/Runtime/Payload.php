<?php

declare(strict_types=1);

namespace Fundly\Integration\Runtime;

use Fundly\Shared\Money\Money;

/** Turns canonical DTOs (and lists of them) into plain arrays for logging. */
final class Payload
{
    public static function toArray(mixed $value): mixed
    {
        if ($value instanceof Money) {
            return $value->toArray();
        }
        if (is_object($value)) {
            $value = get_object_vars($value);
        }
        if (is_array($value)) {
            return array_map(self::toArray(...), $value);
        }

        return $value;
    }

    /** @return array<array-key, mixed>|null */
    public static function forLog(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }
        $converted = self::toArray($value);

        return is_array($converted) ? $converted : ['value' => $converted];
    }
}

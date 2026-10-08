<?php

declare(strict_types=1);

namespace Fundly\Shared\Json;

use JsonException;

/**
 * Deterministic JSON encoding used for hashing (audit chain, payload hashes).
 *
 * Rules: object keys sorted by byte order at every depth; lists keep their
 * order; no insignificant whitespace; slashes and unicode unescaped. Equal
 * data always yields identical bytes, whatever the key order it arrived in
 * (e.g. after a round trip through PostgreSQL jsonb).
 */
final class CanonicalJson
{
    /** @throws JsonException */
    public static function encode(mixed $value): string
    {
        return json_encode(
            self::normalise($value),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION,
        );
    }

    public static function hash(mixed $value): string
    {
        return hash('sha256', self::encode($value));
    }

    private static function normalise(mixed $value): mixed
    {
        if ($value instanceof \JsonSerializable) {
            $value = $value->jsonSerialize();
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d\TH:i:s.uP');
        }
        if (is_object($value)) {
            $value = get_object_vars($value);
            if ($value === []) {
                return new \stdClass;
            }
        }
        if (! is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map(self::normalise(...), $value);
        }
        ksort($value, SORT_STRING);

        return array_map(self::normalise(...), $value);
    }
}

<?php

declare(strict_types=1);

namespace Fundly\Shared\Id;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * RFC 9562 UUID version 7 (Unix epoch milliseconds + random), time-ordered.
 * Pure PHP so the domain layer does not depend on the framework.
 */
final class UuidV7
{
    private const PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';

    public static function generate(?DateTimeImmutable $at = null): string
    {
        // 'Uv' = epoch seconds followed by 3-digit milliseconds: integer maths only.
        $ms = (int) ($at ?? new DateTimeImmutable('now'))->format('Uv');

        $time = str_pad(dechex($ms), 12, '0', STR_PAD_LEFT);
        $rand = random_bytes(10);

        // version 7 in the high nibble of byte 6
        $randA = (ord($rand[0]) & 0x0F) << 8 | ord($rand[1]);
        // variant 10xx in the high bits of byte 8
        $randB0 = (ord($rand[2]) & 0x3F) | 0x80;

        return sprintf(
            '%s-%s-%04x-%02x%s-%s',
            substr($time, 0, 8),
            substr($time, 8, 4),
            0x7000 | $randA,
            $randB0,
            bin2hex($rand[3]),
            bin2hex(substr($rand, 4, 6)),
        );
    }

    public static function isValid(string $value): bool
    {
        return preg_match(self::PATTERN, strtolower($value)) === 1;
    }

    public static function assertValid(string $value): string
    {
        if (! self::isValid($value)) {
            throw new InvalidArgumentException('Invalid UUID.');
        }

        return strtolower($value);
    }

    /** Milliseconds since the Unix epoch encoded in a v7 UUID. */
    public static function timestampMs(string $uuid): int
    {
        $hex = str_replace('-', '', self::assertValid($uuid));

        return (int) hexdec(substr($hex, 0, 12));
    }
}

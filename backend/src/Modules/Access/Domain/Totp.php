<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Domain;

use InvalidArgumentException;

/**
 * RFC 6238 TOTP (HMAC-SHA1, 30 s step, 6 digits) over RFC 4226 HOTP, with
 * RFC 4648 base32 secrets. Implemented here (no third-party dependency) and
 * verified against the RFC test vectors.
 */
final class Totp
{
    public const PERIOD = 30;

    public const DIGITS = 6;

    private const BASE32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function generateSecret(int $bytes = 20): string
    {
        return self::base32Encode(random_bytes($bytes));
    }

    public static function timeStep(int $unixTime): int
    {
        return intdiv($unixTime, self::PERIOD);
    }

    public static function hotp(string $rawKey, int $counter, int $digits = self::DIGITS, string $algo = 'sha1'): string
    {
        $binCounter = pack('J', $counter);
        $hash = hash_hmac($algo, $binCounter, $rawKey, true);
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $code = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);

        return str_pad((string) ($code % (10 ** $digits)), $digits, '0', STR_PAD_LEFT);
    }

    public static function codeAt(string $base32Secret, int $unixTime): string
    {
        return self::hotp(self::base32Decode($base32Secret), self::timeStep($unixTime));
    }

    /**
     * Verifies a code within ±$window steps. Returns the matched step so the
     * caller can reject replays (a step may be used once); null if invalid.
     */
    public static function verify(string $base32Secret, string $code, int $unixTime, ?int $lastUsedStep = null, int $window = 1): ?int
    {
        if (preg_match('/^\d{6}$/', $code) !== 1) {
            return null;
        }
        $key = self::base32Decode($base32Secret);
        $current = self::timeStep($unixTime);
        for ($i = -$window; $i <= $window; $i++) {
            $step = $current + $i;
            if ($lastUsedStep !== null && $step <= $lastUsedStep) {
                continue;
            }
            if (hash_equals(self::hotp($key, $step), $code)) {
                return $step;
            }
        }

        return null;
    }

    public static function provisioningUri(string $base32Secret, string $account, string $issuer): string
    {
        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=SHA1&digits=%d&period=%d',
            rawurlencode($issuer),
            rawurlencode($account),
            $base32Secret,
            rawurlencode($issuer),
            self::DIGITS,
            self::PERIOD,
        );
    }

    public static function base32Encode(string $data): string
    {
        $bits = '';
        foreach (str_split($data) as $c) {
            $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::BASE32[bindec(str_pad($chunk, 5, '0'))];
        }
        if ($data === '') {
            return '';
        }
        $pad = (8 - (strlen($out) % 8)) % 8;

        return $out.str_repeat('=', $pad);
    }

    public static function base32Decode(string $input): string
    {
        $input = strtoupper(rtrim(str_replace(' ', '', $input), '='));
        $bits = '';
        foreach (str_split($input) as $c) {
            if ($c === '') {
                continue;
            }
            $pos = strpos(self::BASE32, $c);
            if ($pos === false) {
                throw new InvalidArgumentException('Invalid base32 character.');
            }
            $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr((int) bindec($byte));
            }
        }

        return $out;
    }
}

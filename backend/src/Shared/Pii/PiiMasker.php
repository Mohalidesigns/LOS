<?php

declare(strict_types=1);

namespace Fundly\Shared\Pii;

/**
 * Masks PII by default (FR-CMP-036) in audit before/after values, integration
 * call logs and exports. Pure and deterministic.
 *
 * - "mask" keys keep a short prefix/suffix for operator recognition, e.g.
 *   BVN 22345678991 → "2234*****91" (TRD §5.3);
 * - "redact" keys (secrets, credentials, OTPs) are replaced entirely.
 */
final class PiiMasker
{
    public const REDACTED = '[REDACTED]';

    /** @var array<string, true> */
    private array $mask;

    /** @var array<string, true> */
    private array $redact;

    /**
     * @param  list<string>  $maskKeys
     * @param  list<string>  $redactKeys
     */
    public function __construct(array $maskKeys, array $redactKeys)
    {
        $this->mask = array_fill_keys(array_map(self::normaliseKey(...), $maskKeys), true);
        $this->redact = array_fill_keys(array_map(self::normaliseKey(...), $redactKeys), true);
    }

    /**
     * @param  array<array-key, mixed>|null  $data
     * @return array<array-key, mixed>|null
     */
    public function mask(?array $data): ?array
    {
        if ($data === null) {
            return null;
        }

        $out = [];
        foreach ($data as $key => $value) {
            $normalised = is_string($key) ? self::normaliseKey($key) : null;
            if ($normalised !== null && isset($this->redact[$normalised])) {
                $out[$key] = $value === null ? null : self::REDACTED;
            } elseif ($normalised !== null && isset($this->mask[$normalised]) && ! is_array($value)) {
                $out[$key] = $value === null ? null : self::maskValue((string) (is_scalar($value) ? $value : json_encode($value)));
            } elseif (is_array($value)) {
                $out[$key] = $this->mask($value);
            } else {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    public static function maskValue(string $value): string
    {
        $len = mb_strlen($value);
        if ($len <= 4) {
            return str_repeat('*', $len);
        }
        if (str_contains($value, '@')) {
            [$local, $domain] = explode('@', $value, 2);

            return mb_substr($local, 0, 1).str_repeat('*', max(mb_strlen($local) - 1, 1)).'@'.$domain;
        }
        $keepHead = $len >= 10 ? 4 : 1;
        $keepTail = 2;

        return mb_substr($value, 0, $keepHead).str_repeat('*', $len - $keepHead - $keepTail).mb_substr($value, -$keepTail);
    }

    private static function normaliseKey(string $key): string
    {
        return strtolower(str_replace(['-', ' '], '_', $key));
    }
}

<?php

declare(strict_types=1);

namespace Fundly\Shared\Bus;

use Fundly\Shared\Exceptions\ValidationFailed;

/** Typed, validated reads from stored command / change-request payloads. */
final class Payload
{
    /** @param array<string, mixed> $payload */
    public static function string(array $payload, string $key): string
    {
        $v = $payload[$key] ?? null;
        if (! is_string($v) || $v === '') {
            throw ValidationFailed::with([$key => "{$key} is required."]);
        }

        return $v;
    }

    /** @param array<string, mixed> $payload */
    public static function optionalString(array $payload, string $key): ?string
    {
        $v = $payload[$key] ?? null;

        return is_string($v) && $v !== '' ? $v : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    public static function strings(array $payload, string $key): array
    {
        $v = $payload[$key] ?? [];
        if (! is_array($v)) {
            throw ValidationFailed::with([$key => "{$key} must be a list."]);
        }

        return array_values(array_filter($v, 'is_string'));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function map(array $payload, string $key): array
    {
        $v = $payload[$key] ?? [];
        if (! is_array($v)) {
            throw ValidationFailed::with([$key => "{$key} must be an object."]);
        }
        $out = [];
        foreach ($v as $k => $item) {
            $out[(string) $k] = $item;
        }

        return $out;
    }
}

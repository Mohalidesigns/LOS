<?php

declare(strict_types=1);

namespace Fundly\Modules\Document\Domain;

use Fundly\Shared\Exceptions\ValidationFailed;

/**
 * Format and size policy (FR-DOC-002). The media type is the one sniffed
 * from the bytes, never the one the client claims.
 */
final class UploadPolicy
{
    /** @param array<string, string> $allowed media type → extension */
    public static function assertAcceptable(string $sniffedType, int $size, int $maxBytes, array $allowed): void
    {
        if ($size === 0) {
            throw ValidationFailed::with(['file' => 'The file is empty.']);
        }
        if ($size > $maxBytes) {
            throw ValidationFailed::with(['file' => sprintf('The file is %s; the limit is %s.', self::human($size), self::human($maxBytes))]);
        }
        if (! isset($allowed[$sniffedType])) {
            throw ValidationFailed::with(['file' => "Files of type {$sniffedType} are not accepted."]);
        }
    }

    public static function safeFilename(string $name): string
    {
        $base = basename(str_replace('\\', '/', $name));
        $clean = preg_replace('/[^\pL\pN ._()-]+/u', '_', $base) ?? 'document';

        return mb_substr(trim($clean) === '' ? 'document' : trim($clean), 0, 200);
    }

    private static function human(int $bytes): string
    {
        return $bytes >= 1048576 ? sprintf('%d.%d MB', intdiv($bytes, 1048576), intdiv(($bytes % 1048576) * 10, 1048576)) : intdiv($bytes, 1024).' KB';
    }
}

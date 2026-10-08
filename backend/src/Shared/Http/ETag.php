<?php

declare(strict_types=1);

namespace Fundly\Shared\Http;

use Fundly\Shared\Exceptions\PreconditionFailed;
use Fundly\Shared\Exceptions\PreconditionRequired;
use Fundly\Shared\Json\CanonicalJson;
use Illuminate\Http\Request;

/**
 * Optimistic concurrency (TRD §10.1): resources carry an ETag; PATCH/PUT and
 * aggregate commands must send If-Match. Missing → 428, stale → 412.
 */
final class ETag
{
    public static function of(string $id, string|int $version): string
    {
        return '"'.substr(hash('sha256', $id.'|'.$version), 0, 32).'"';
    }

    /**
     * Strong validator over the full representation: any visible change changes the tag.
     *
     * @param  array<string, mixed>  $representation
     */
    public static function forRepresentation(string $id, array $representation): string
    {
        return self::of($id, CanonicalJson::hash($representation));
    }

    public static function assertMatches(Request $request, string $current): void
    {
        $header = $request->headers->get('If-Match');
        self::assertHeader(is_string($header) ? $header : null, $current);
    }

    /** For handlers that compare after locking the row (no check-then-write race). */
    public static function assertHeader(?string $ifMatch, string $current): void
    {
        if (! is_string($ifMatch) || trim($ifMatch) === '') {
            throw new PreconditionRequired('This operation requires an If-Match header with the resource ETag.');
        }
        $candidates = array_map(static fn (string $t): string => trim(preg_replace('/^W\//', '', trim($t)) ?? ''), explode(',', $ifMatch));
        if (! in_array('*', $candidates, true) && ! in_array($current, $candidates, true)) {
            throw new PreconditionFailed('The resource has changed since it was read (ETag mismatch).', ['current_etag' => $current]);
        }
    }
}

<?php

declare(strict_types=1);

namespace Fundly\Shared\Http;

use Illuminate\Http\JsonResponse;

final class ApiResponse
{
    /** @param array<array-key, mixed> $body */
    public static function json(array $body, int $status = 200, ?string $etag = null): JsonResponse
    {
        $response = new JsonResponse($body, $status, [], JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
        if ($etag !== null) {
            $response->headers->set('ETag', $etag);
        }

        return $response;
    }

    /** @param array{data: array<string, mixed>, etag?: string} $result */
    public static function resource(array $result, int $status = 200): JsonResponse
    {
        return self::json(['data' => $result['data']], $status, $result['etag'] ?? null);
    }

    /** 202 Accepted with the change request that now awaits a checker. */
    public static function changeRequest(mixed $changeRequest): JsonResponse
    {
        return self::json(['data' => is_array($changeRequest) ? $changeRequest : []], 202);
    }

    public static function noContent(): JsonResponse
    {
        return new JsonResponse(null, 204);
    }
}

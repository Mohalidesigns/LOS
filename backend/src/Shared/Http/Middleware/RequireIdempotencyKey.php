<?php

declare(strict_types=1);

namespace Fundly\Shared\Http\Middleware;

use Closure;
use Fundly\Shared\Exceptions\CodedConflict;
use Fundly\Shared\Exceptions\ValidationFailed;
use Fundly\Shared\Idempotency\IdempotencyStore;
use Fundly\Shared\Json\CanonicalJson;
use Fundly\Shared\Security\CurrentPrincipal;
use Illuminate\Http\Request;
use Illuminate\Http\Response as IlluminateResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * `Idempotency-Key` is required on POST commands with an external effect
 * (PR-05, TRD §10.1). A replay with the same body returns the stored
 * response; the same key with a different body returns 409.
 */
final class RequireIdempotencyKey
{
    public const HEADER = 'Idempotency-Key';

    private const SCOPE = 'http';

    public function __construct(
        private readonly IdempotencyStore $store,
        private readonly CurrentPrincipal $principal,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->headers->get(self::HEADER);
        if (! is_string($key) || preg_match('/^[A-Za-z0-9._:-]{8,255}$/', $key) !== 1) {
            throw ValidationFailed::with([self::HEADER => 'An Idempotency-Key header (8-255 chars of [A-Za-z0-9._:-]) is required for this operation.']);
        }

        $principalId = $this->principal->require()->id;
        $hash = hash('sha256', $request->method().' '.$request->path()."\n".CanonicalJson::encode($request->json()->all()));
        $reservation = $this->store->reserve(self::SCOPE, $principalId, $key, $hash);

        if (! $reservation['reserved']) {
            $record = $reservation['record'];
            if (! is_object($record) || ! hash_equals((string) $record->request_hash, $hash)) {
                throw new CodedConflict('idempotency-key-reused', 'This Idempotency-Key was already used with a different request.');
            }
            if ($record->status !== 'completed') {
                throw new CodedConflict('idempotency-in-progress', 'A request with this Idempotency-Key is still being processed.');
            }
            /** @var array<string, string> $headers */
            $headers = json_decode((string) $record->response_headers, true) ?: [];
            $replay = new IlluminateResponse((string) $record->response_body, (int) $record->response_status, $headers);
            $replay->headers->set('Idempotent-Replayed', 'true');

            return $replay;
        }

        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            $this->store->release(self::SCOPE, $principalId, $key);
            throw $e;
        }

        if ($response->getStatusCode() >= 500) {
            $this->store->release(self::SCOPE, $principalId, $key);
        } else {
            $headers = array_filter([
                'Content-Type' => $response->headers->get('Content-Type'),
                'Location' => $response->headers->get('Location'),
                'ETag' => $response->headers->get('ETag'),
            ], fn ($v): bool => is_string($v));
            $this->store->complete(self::SCOPE, $principalId, $key, $response->getStatusCode(), $headers, (string) $response->getContent());
        }

        return $response;
    }
}

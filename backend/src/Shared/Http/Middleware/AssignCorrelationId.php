<?php

declare(strict_types=1);

namespace Fundly\Shared\Http\Middleware;

use Closure;
use Fundly\Shared\Http\RequestContext;
use Fundly\Shared\Id\UuidV7;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Accepts a well-formed X-Correlation-Id or generates one, echoes it on the
 * response and shares it with logs, audit and integration calls (TRD §10.1).
 */
final class AssignCorrelationId
{
    public const HEADER = 'X-Correlation-Id';

    public function __construct(private readonly RequestContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $incoming = $request->headers->get(self::HEADER);
        $id = is_string($incoming) && preg_match('/^[A-Za-z0-9._:-]{8,64}$/', $incoming) === 1
            ? $incoming
            : UuidV7::generate();

        $this->context->setCorrelationId($id);
        $device = $request->headers->get('X-Device-Id');
        $this->context->setClient($request->ip(), $request->userAgent(), is_string($device) ? $device : null);
        Log::withContext(['correlation_id' => $id]);

        $response = $next($request);
        $response->headers->set(self::HEADER, $id);

        return $response;
    }
}

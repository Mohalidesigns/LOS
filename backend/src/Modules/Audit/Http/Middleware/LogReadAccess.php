<?php

declare(strict_types=1);

namespace Fundly\Modules\Audit\Http\Middleware;

use Closure;
use Fundly\Modules\Audit\Application\ReadAccessLog;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `access.log:<resource_type>[,pii]` on read routes (FR-AUD-006). Records a
 * successful read of the route's `{id}` (or of a list when there is none),
 * after the response is produced; refused or failed reads are not access.
 */
final class LogReadAccess
{
    public function __construct(private readonly ReadAccessLog $log) {}

    public function handle(Request $request, Closure $next, string $resourceType, string $pii = ''): Response
    {
        $purpose = ReadAccessLog::purpose($request->headers->get('X-Access-Purpose'));
        $response = $next($request);
        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            $id = $request->route('id');
            $name = $request->route()?->getName();
            $this->log->record($resourceType, is_string($id) ? $id : null, is_string($name) ? $name : $request->path(), $pii === 'pii', $purpose);
        }

        return $response;
    }
}

<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Http\Middleware;

use Closure;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Audit\AuditOutcome;
use Fundly\Shared\Audit\AuditTrail;
use Fundly\Shared\Security\AccessDenied;
use Fundly\Shared\Security\AuthorizationGate;
use Fundly\Shared\Security\CurrentPrincipal;
use Fundly\Shared\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level authorisation: `authz:<permission>` (principal must hold it in
 * some scope), `authz:authenticated` (any authenticated principal, e.g. /me),
 * or `authz:public` (health, login). Every route must declare one; an
 * architecture test enforces it (FR-SEC-014). Denials are audited (FR-AUD-007).
 */
final class Authorize
{
    public const PUBLIC = 'public';

    public const AUTHENTICATED = 'authenticated';

    public function __construct(
        private readonly AuthorizationGate $gate,
        private readonly CurrentPrincipal $principal,
        private readonly AuditTrail $audit,
        private readonly TenantContext $tenant,
    ) {
    }

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if ($permission === self::PUBLIC) {
            return $next($request);
        }
        $principal = $this->principal->require();
        if ($permission === self::AUTHENTICATED) {
            return $next($request);
        }

        $decision = $this->gate->check($principal, $permission);
        if (! $decision->allowed) {
            if ($this->tenant->has()) {
                $this->audit->record(new AuditEntry(
                    action: 'authz.denied',
                    outcome: AuditOutcome::Denied,
                    after: ['route' => $request->method().' /'.$request->path(), 'reason' => $decision->reason],
                    permission: $permission,
                    reasonCode: $decision->reason,
                ));
            }
            throw new AccessDenied($permission, $decision->reason);
        }

        return $next($request);
    }
}

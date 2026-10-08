<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Http\Middleware;

use Closure;
use Fundly\Modules\Platform\Contracts\TenantDirectory;
use Fundly\Shared\Security\CurrentPrincipal;
use Fundly\Shared\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Establishes the tenant context *before* authentication, from the credential
 * itself (never the request body), so the RLS-protected user row can be read:
 *
 *  - session: the tenant stored at sign-in;
 *  - bearer token: the tenant recorded on the token row;
 *  - neither (e.g. login): the installation tenant (D-033).
 *
 * BindPrincipal later re-checks it against the authenticated user. The
 * context is restored when the request ends, so nothing leaks between
 * requests on a persistent worker.
 */
final class ResolveTenantFromCredential
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly TenantDirectory $tenants,
        private readonly CurrentPrincipal $principal,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $previous = $this->tenant->id();
        $tenantId = $this->fromSession($request) ?? $this->fromBearer($request) ?? $this->tenants->resolveForGuest($request->getHost());

        if ($tenantId !== null && $this->tenants->isActive($tenantId)) {
            $this->tenant->set($tenantId);
        }

        try {
            return $next($request);
        } finally {
            $this->principal->set(null);
            $this->tenant->restore($previous);
        }
    }

    private function fromSession(Request $request): ?string
    {
        if (! $request->hasSession()) {
            return null;
        }
        $session = $request->session();
        $tenant = $session->get('auth.tenant_id');
        if (is_string($tenant)) {
            return $tenant;
        }
        $pending = $session->get('auth.pending');

        return is_array($pending) && isset($pending['tenant_id']) && is_string($pending['tenant_id']) ? $pending['tenant_id'] : null;
    }

    private function fromBearer(Request $request): ?string
    {
        $token = $request->bearerToken();
        if ($token === null || ! str_contains($token, '|')) {
            return null;
        }
        [$id] = explode('|', $token, 2);
        if (! ctype_digit($id)) {
            return null;
        }
        $tenant = DB::table('personal_access_tokens')->where('id', (int) $id)->value('tenant_id');

        return is_string($tenant) ? $tenant : null;
    }
}

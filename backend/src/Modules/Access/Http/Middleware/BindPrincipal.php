<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Http\Middleware;

use Closure;
use DateTimeImmutable;
use Fundly\Modules\Access\Infrastructure\Models\PersonalAccessToken;
use Fundly\Modules\Access\Infrastructure\Models\User;
use Fundly\Shared\Exceptions\Unauthenticated;
use Fundly\Shared\Security\CurrentPrincipal;
use Fundly\Shared\Security\Principal;
use Fundly\Shared\Security\PrincipalKind;
use Fundly\Shared\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Turns the authenticated user into the request Principal, with the tenant
 * taken from the authenticated user (TRD §2.3) and token abilities narrowing
 * access for service/partner credentials (FR-SEC-014).
 */
final class BindPrincipal
{
    public function __construct(private readonly CurrentPrincipal $principal, private readonly TenantContext $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user instanceof User || ! $user->isActive()) {
            throw new Unauthenticated('Authentication required.');
        }
        if ($this->tenant->id() !== $user->tenant_id) {
            $this->tenant->set($user->tenant_id);
        }

        $token = $user->currentAccessToken();
        $abilities = null;
        $stepUpAt = null;
        $stepUpRef = null;
        if ($token instanceof PersonalAccessToken) {
            /** @var list<string> $abilities */
            $abilities = array_values($token->abilities ?? []);
        } elseif ($request->hasSession()) {
            $at = $request->session()->get('auth.step_up_at');
            $ref = $request->session()->get('auth.step_up_ref');
            $stepUpAt = is_int($at) ? (new DateTimeImmutable)->setTimestamp($at) : null;
            $stepUpRef = is_string($ref) ? $ref : null;
        }

        $this->principal->set(new Principal(
            $user->id,
            $user->tenant_id,
            $user->isService() ? PrincipalKind::Service : PrincipalKind::Human,
            $abilities,
            $stepUpAt,
            $stepUpRef,
        ));

        return $next($request);
    }
}

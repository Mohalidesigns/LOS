<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Http\Middleware;

use Closure;
use Fundly\Modules\Access\Application\Auth\SessionExpired;
use Fundly\Modules\Access\Application\Auth\SessionPolicyProvider;
use Fundly\Modules\Access\Infrastructure\Models\PersonalAccessToken;
use Fundly\Modules\Access\Infrastructure\Models\User;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Audit\AuditTrail;
use Fundly\Shared\Clock\Clock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Idle and absolute timeouts, and forced re-authentication after a
 * privilege change (auth_version mismatch) for staff sessions (FR-SEC-015).
 */
final class EnforceSessionPolicy
{
    public function __construct(
        private readonly SessionPolicyProvider $policy,
        private readonly Clock $clock,
        private readonly AuditTrail $audit,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user instanceof User || $user->currentAccessToken() instanceof PersonalAccessToken || ! $request->hasSession()) {
            return $next($request);
        }
        $session = $request->session();
        $now = $this->clock->now()->getTimestamp();

        $reason = null;
        if ($session->get('auth.version') !== $user->auth_version) {
            $reason = 'revoked';
        } else {
            $at = $session->get('auth.at');
            $last = $session->get('auth.last_activity');
            $reason = $this->policy->current()->violation(is_int($at) ? $at : 0, is_int($last) ? $last : 0, $now);
        }

        if ($reason !== null) {
            $this->audit->record(new AuditEntry(action: 'auth.session.ended', entityType: 'user', entityId: $user->id, after: ['reason' => $reason]));
            Auth::guard('web')->logout();
            $session->invalidate();
            throw new SessionExpired($reason);
        }

        $session->put('auth.last_activity', $now);

        return $next($request);
    }
}

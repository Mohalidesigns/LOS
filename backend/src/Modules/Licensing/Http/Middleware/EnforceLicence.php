<?php

declare(strict_types=1);

namespace Fundly\Modules\Licensing\Http\Middleware;

use Closure;
use Fundly\Modules\Licensing\Application\LicenceGuard;
use Fundly\Modules\Licensing\Application\LicenceRecovery;
use Fundly\Modules\Licensing\Domain\LicenceProblem;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Audit\AuditOutcome;
use Fundly\Shared\Audit\AuditTrail;
use Fundly\Shared\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * `licence` / `licence:<module>`: validity (with grace) and module entitlement
 * per route group (TRD §2.6). Exempt (D-034 fail-safe): routes marked
 * `licence.exempt` (auditor read-only access, evidence, licence recovery) and
 * the approval of a licence-import change request.
 */
final class EnforceLicence
{
    public const STATE_HEADER = 'Fundly-Licence-State';

    public function __construct(
        private readonly LicenceGuard $guard,
        private readonly LicenceRecovery $recovery,
        private readonly AuditTrail $audit,
        private readonly TenantContext $tenant,
    ) {}

    public function handle(Request $request, Closure $next, ?string $module = null): Response
    {
        $state = $this->guard->state();
        $route = $request->route();
        $exempt = $route instanceof Route && (in_array('licence.exempt', $route->gatherMiddleware(), true) || $this->recovery->isRecoveryRequest($route));

        if (! $exempt) {
            try {
                $this->guard->assertOperational();
                if ($module !== null) {
                    $this->guard->assertModule($module);
                }
            } catch (LicenceProblem $blocked) {
                if ($this->tenant->has()) {
                    $this->audit->record(new AuditEntry(
                        action: 'licensing.enforcement.blocked',
                        outcome: AuditOutcome::Denied,
                        after: ['route' => $request->method().' /'.$request->path(), 'reason' => $blocked->type(), 'licence_state' => $state->value],
                        reasonCode: $blocked->type(),
                    ));
                }
                throw $blocked;
            }
        }

        $response = $next($request);
        $response->headers->set(self::STATE_HEADER, $state->value);

        return $response;
    }
}

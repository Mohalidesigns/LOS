<?php

declare(strict_types=1);

namespace Fundly\Modules\Licensing\Application;

use Fundly\Integration\Ports\Licensing\Licence;
use Fundly\Integration\Ports\Licensing\LicenceState;
use Fundly\Integration\Ports\Licensing\LicensingPort;
use Fundly\Modules\Licensing\Contracts\LicenceEntitlements;
use Fundly\Modules\Licensing\Domain\LicenceProblem;
use Fundly\Shared\Clock\Clock;

/**
 * Licence enforcement points (TRD §2.6): login (validity + named-user cap),
 * module route groups (entitlement), user creation (cap). Fail-safe rules
 * (D-034) are applied by the callers' exemptions, never by weakening checks.
 */
final class LicenceGuard implements LicenceEntitlements
{
    public function __construct(private readonly LicensingPort $port, private readonly Clock $clock) {}

    public function licence(): ?Licence
    {
        return $this->port->currentLicence();
    }

    public function state(): LicenceState
    {
        $licence = $this->port->currentLicence();

        return $licence === null ? LicenceState::NotInstalled : $licence->state($this->clock->now());
    }

    public function assertOperational(): void
    {
        $state = $this->state();
        if (! $state->permitsOperation()) {
            throw new LicenceProblem(
                $state === LicenceState::NotInstalled ? 'licence-not-installed' : 'licence-expired',
                $state === LicenceState::NotInstalled ? 'No valid licence is installed.' : 'The licence is not valid ('.$state->value.'); only read-only and recovery operations are available.',
                ['licence_state' => $state->value],
            );
        }
    }

    public function assertModule(string $module): void
    {
        $licence = $this->port->currentLicence();
        if ($licence !== null && ! $licence->entitles($module)) {
            throw new LicenceProblem('licence-module-not-entitled', "This installation is not licensed for the {$module} module.", ['module' => $module]);
        }
    }

    public function assertCanAddNamedUser(int $currentActiveNamedUsers): void
    {
        $max = $this->maxNamedUsers();
        if ($max !== null && $currentActiveNamedUsers + 1 > $max) {
            throw new LicenceProblem('licence-user-cap-reached', "The licence allows {$max} named users.", ['max_named_users' => $max]);
        }
    }

    public function assertLoginAllowed(int $activeNamedUsers, bool $readOnlyPrincipal, bool $mayManageUsers, bool $mayRecoverLicence = false): void
    {
        if ($readOnlyPrincipal) {
            return; // auditor / regulator read access is never blocked (D-034)
        }
        if ($mayRecoverLicence && ! $this->state()->permitsOperation()) {
            // Recovery sign-in: without it nobody could approve the licence import
            // that ends the outage. EnforceLicence still blocks every non-exempt route.
            return;
        }
        $this->assertOperational();
        $max = $this->maxNamedUsers();
        if ($max !== null && $activeNamedUsers > $max && ! $mayManageUsers) {
            throw new LicenceProblem('licence-user-cap-exceeded', "There are {$activeNamedUsers} active named users but the licence allows {$max}. An administrator must deactivate users.", ['max_named_users' => $max]);
        }
    }

    public function maxNamedUsers(): ?int
    {
        return $this->port->currentLicence()?->maxNamedUsers;
    }

    public function allowsModule(string $module): bool
    {
        return $this->port->currentLicence()?->entitles($module) ?? false;
    }
}

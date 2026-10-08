<?php

declare(strict_types=1);

namespace Fundly\Modules\Licensing\Contracts;

/** What other modules may ask the licensing module (LOS-FR-316, D-034). */
interface LicenceEntitlements
{
    /** Throws a licence problem if one more active named (human) user would exceed the cap. */
    public function assertCanAddNamedUser(int $currentActiveNamedUsers): void;

    /**
     * Login enforcement: validity (beyond grace blocks) and the named-user cap.
     * Fail-safe (D-034): read-only auditor/regulator access is never blocked;
     * a user who can manage users may still sign in to bring the installation
     * back under its cap.
     */
    public function assertLoginAllowed(int $activeNamedUsers, bool $readOnlyPrincipal, bool $mayManageUsers): void;

    public function maxNamedUsers(): ?int;

    public function allowsModule(string $module): bool;
}

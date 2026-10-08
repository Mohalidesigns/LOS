<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Contracts;

/** Installation-time bootstrap of a tenant's access model (used by tenant provisioning). */
interface AccessProvisioning
{
    /** Seeds the standard role library templates and default SoD rules. */
    public function seedTenant(): void;

    /**
     * Creates a bootstrap administrator holding the Tenant Administrator role,
     * attributed to system:installer. Returns the user id.
     */
    public function bootstrapAdministrator(string $email, string $name, string $password): string;
}

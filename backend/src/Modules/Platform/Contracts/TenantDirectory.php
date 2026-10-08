<?php

declare(strict_types=1);

namespace Fundly\Modules\Platform\Contracts;

interface TenantDirectory
{
    /**
     * Tenant for an unauthenticated request (e.g. login). Installation = tenant
     * (D-033): in "single" mode this is the sole active tenant; in "hostname"
     * mode it is matched on the request host. Never taken from a request body.
     */
    public function resolveForGuest(string $host): ?string;

    public function isActive(string $tenantId): bool;

    /** @return list<string> */
    public function activeTenantIds(): array;
}

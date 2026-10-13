<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Contracts;

use Fundly\Shared\Security\ResourceAttributes;

/**
 * Who holds a permission for a resource, and through which grant. Approvals
 * use it to work out authority (role, limit, delegation) without reaching
 * into Access internals. Scope is matched on every dimension except amount:
 * amount authority is the caller's business (effective limits, FR-APV-002).
 */
interface AuthorityDirectory
{
    /** @return list<AuthorityGrant> the user's active grants of the permission that cover the resource */
    public function grantsFor(string $userId, string $permission, ResourceAttributes $resource): array;

    /** @return list<AuthorityGrant> every active user's grants of the permission that cover the resource */
    public function holders(string $permission, ResourceAttributes $resource): array;

    /**
     * Staff display names for ids; ids that are not users (system identities) are omitted.
     *
     * @param  list<string>  $userIds
     * @return array<string, string>
     */
    public function names(array $userIds): array;
}

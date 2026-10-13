<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Contracts;

use Fundly\Shared\Money\Money;

/**
 * One active grant through which a user holds a permission for a resource:
 * their own role assignment, or one delegated to them (FR-APV-006). The
 * scope's max amount is the user-level authority limit (FR-APV-002).
 */
final readonly class AuthorityGrant
{
    public function __construct(
        public string $userId,
        public string $assignmentId,
        public string $roleCode,
        public string $roleName,
        public ?Money $scopeMaxAmount,
        public ?string $homeOrgUnitId,
        public ?string $delegationId = null,
        public ?string $delegatedBy = null,
    ) {}

    public function isDelegated(): bool
    {
        return $this->delegationId !== null;
    }
}

<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Domain;

use DateTimeImmutable;

/**
 * An effective grant: a role assignment (own or delegated) with its permission
 * set, scope and validity window. The org subtree is resolved by
 * infrastructure (closure table) so the policy stays pure.
 */
final readonly class Grant
{
    /**
     * @param  list<string>  $permissions
     * @param  list<string>|null  $orgSubtree  ids of the scope org unit and all its descendants
     */
    public function __construct(
        public string $assignmentId,
        public string $roleId,
        public string $roleCode,
        public array $permissions,
        public Scope $scope,
        public DateTimeImmutable $validFrom,
        public ?DateTimeImmutable $validTo,
        public ?array $orgSubtree = null,
        public ?string $delegatedBy = null,
        public ?string $delegationId = null,
    ) {
    }

    public function isActiveAt(DateTimeImmutable $now): bool
    {
        return $this->validFrom <= $now && ($this->validTo === null || $now < $this->validTo);
    }

    public function grants(string $permission): bool
    {
        return in_array($permission, $this->permissions, true);
    }
}

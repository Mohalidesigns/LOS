<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Application;

use Fundly\Modules\Access\Contracts\AuthorityDirectory;
use Fundly\Modules\Access\Contracts\AuthorityGrant;
use Fundly\Modules\Access\Domain\AccessPolicy;
use Fundly\Modules\Access\Domain\Grant;
use Fundly\Modules\Access\Infrastructure\Persistence\GrantRepository;
use Fundly\Shared\Clock\Clock;
use Fundly\Shared\Security\ResourceAttributes;
use Illuminate\Database\ConnectionInterface;

final class GrantAuthorityDirectory implements AuthorityDirectory
{
    public function __construct(private readonly GrantRepository $grants, private readonly ConnectionInterface $db, private readonly Clock $clock) {}

    public function grantsFor(string $userId, string $permission, ResourceAttributes $resource): array
    {
        $unbounded = new ResourceAttributes(
            legalEntityId: $resource->legalEntityId, orgUnitId: $resource->orgUnitId, productId: $resource->productId,
            currency: $resource->currency, segment: $resource->segment, portfolioTags: $resource->portfolioTags,
            entityType: $resource->entityType, entityId: $resource->entityId,
        );
        $now = $this->clock->now();
        $home = null;
        $out = [];
        foreach ($this->grants->forUser($userId) as $grant) {
            if (! $grant->isActiveAt($now) || ! $grant->grants($permission) || ! AccessPolicy::inScope($grant, $unbounded)) {
                continue;
            }
            $home ??= ['id' => $this->db->table('users')->where('id', $userId)->value('home_org_unit_id')];
            $out[] = $this->toAuthority($userId, $grant, is_string($home['id']) ? $home['id'] : null);
        }

        return $out;
    }

    public function holders(string $permission, ResourceAttributes $resource): array
    {
        $roleIds = $this->db->table('role_permissions')->where('permission_code', $permission)->pluck('role_id');
        $assignmentIds = $this->db->table('role_assignments')->whereIn('role_id', $roleIds)->whereNull('revoked_at')->pluck('id');
        $userIds = $this->db->table('role_assignments')->whereIn('id', $assignmentIds)->pluck('user_id')
            ->merge($this->db->table('delegations')->whereIn('role_assignment_id', $assignmentIds)->whereNull('revoked_at')->pluck('delegate_id'))
            ->map(static fn (mixed $id): string => (string) $id)->unique()->sort()->values()->all();
        $out = [];
        foreach ($userIds as $userId) {
            array_push($out, ...$this->grantsFor($userId, $permission, $resource));
        }

        return $out;
    }

    public function names(array $userIds): array
    {
        $ids = array_values(array_filter(array_unique($userIds), static fn (string $id): bool => preg_match('/^[0-9a-f-]{36}$/', $id) === 1));
        if ($ids === []) {
            return [];
        }

        /** @var array<string, string> */
        return $this->db->table('users')->whereIn('id', $ids)->pluck('name', 'id')->map(static fn (mixed $n): string => (string) $n)->all();
    }

    private function toAuthority(string $userId, Grant $grant, ?string $homeOrgUnitId): AuthorityGrant
    {
        return new AuthorityGrant(
            userId: $userId,
            assignmentId: $grant->assignmentId,
            roleCode: $grant->roleCode,
            roleName: (string) $this->db->table('roles')->where('id', $grant->roleId)->value('name'),
            scopeMaxAmount: $grant->scope->maxAmount,
            homeOrgUnitId: $homeOrgUnitId,
            delegationId: $grant->delegationId,
            delegatedBy: $grant->delegatedBy,
        );
    }
}

<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Infrastructure\Persistence;

use DateTimeImmutable;
use Fundly\Modules\Access\Domain\Grant;
use Fundly\Modules\Access\Domain\Scope;
use Fundly\Shared\Clock\Clock;
use Illuminate\Database\ConnectionInterface;

/**
 * Loads a user's grants: own role assignments plus assignments delegated to
 * them (FR-SEC-004, FR-SEC-008). Disabled users have no grants.
 */
final class GrantRepository
{
    /** @var array<string, list<Grant>> */
    private array $cache = [];

    public function __construct(private readonly ConnectionInterface $db, private readonly Clock $clock)
    {
    }

    /**
     * Non-revoked, non-expired grants (including not-yet-valid ones; the
     * policy filters on validity at decision time).
     *
     * @return list<Grant>
     */
    public function forUser(string $userId): array
    {
        return $this->cache[$userId] ??= $this->load($userId);
    }

    public function forget(?string $userId = null): void
    {
        if ($userId === null) {
            $this->cache = [];
        } else {
            unset($this->cache[$userId]);
        }
    }

    /** @return list<Grant> */
    private function load(string $userId): array
    {
        $now = $this->clock->now();
        $active = $this->db->table('users')->where('id', $userId)->where('status', 'active')->exists();
        if (! $active) {
            return [];
        }

        $rows = $this->db->table('role_assignments as a')
            ->join('roles as r', 'r.id', '=', 'a.role_id')
            ->where('a.user_id', $userId)
            ->whereNull('a.revoked_at')
            ->where(fn ($q) => $q->whereNull('a.valid_to')->orWhere('a.valid_to', '>', $now))
            ->get(['a.id', 'a.role_id', 'r.code', 'a.scope', 'a.valid_from', 'a.valid_to']);

        $grants = [];
        foreach ($rows as $row) {
            $grants[] = $this->toGrant($row);
        }

        $delegated = $this->db->table('delegations as d')
            ->join('role_assignments as a', 'a.id', '=', 'd.role_assignment_id')
            ->join('roles as r', 'r.id', '=', 'a.role_id')
            ->join('users as u', 'u.id', '=', 'd.delegator_id')
            ->where('d.delegate_id', $userId)
            ->whereNull('d.revoked_at')
            ->whereNull('a.revoked_at')
            ->where('u.status', 'active')
            ->where('d.valid_to', '>', $now)
            ->where(fn ($q) => $q->whereNull('a.valid_to')->orWhere('a.valid_to', '>', $now))
            ->get(['a.id', 'a.role_id', 'r.code', 'a.scope', 'a.valid_from', 'a.valid_to', 'd.id as delegation_id', 'd.delegator_id', 'd.valid_from as d_from', 'd.valid_to as d_to']);

        foreach ($delegated as $row) {
            $base = $this->toGrant($row);
            $from = max($base->validFrom, new DateTimeImmutable((string) $row->d_from));
            $to = new DateTimeImmutable((string) $row->d_to);
            if ($base->validTo !== null && $base->validTo < $to) {
                $to = $base->validTo;
            }
            $grants[] = new Grant(
                $base->assignmentId, $base->roleId, $base->roleCode, $base->permissions, $base->scope,
                $from, $to, $base->orgSubtree, (string) $row->delegator_id, (string) $row->delegation_id,
            );
        }

        return $grants;
    }

    private function toGrant(object $row): Grant
    {
        /** @var array<string, mixed> $scopeData */
        $scopeData = json_decode((string) $row->scope, true, 16, JSON_THROW_ON_ERROR);
        $scope = Scope::fromArray($scopeData);
        /** @var list<string> $permissions */
        $permissions = $this->db->table('role_permissions')->where('role_id', $row->role_id)->pluck('permission_code')->all();
        $subtree = null;
        if ($scope->orgUnitId !== null) {
            /** @var list<string> $subtree */
            $subtree = $this->db->table('org_unit_closure')->where('ancestor_id', $scope->orgUnitId)->pluck('descendant_id')->all();
        }

        return new Grant(
            assignmentId: (string) $row->id,
            roleId: (string) $row->role_id,
            roleCode: (string) $row->code,
            permissions: $permissions,
            scope: $scope,
            validFrom: new DateTimeImmutable((string) $row->valid_from),
            validTo: $row->valid_to === null ? null : new DateTimeImmutable((string) $row->valid_to),
            orgSubtree: $subtree,
        );
    }
}

<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Application;

use Fundly\Modules\Access\Domain\Grant;
use Fundly\Modules\Access\Domain\SodPolicy;
use Fundly\Modules\Access\Domain\SodRule;
use Fundly\Modules\Access\Infrastructure\Persistence\GrantRepository;
use Fundly\Modules\Access\Infrastructure\Persistence\SodRuleRepository;
use Fundly\Shared\Clock\Clock;
use Illuminate\Database\ConnectionInterface;

/**
 * Assignment-time SoD (FR-SEC-006): evaluates what a user *would* hold after a
 * change, across all their current and future-dated assignments and
 * delegations, and reports conflicts. Also produces the standing conflict
 * report (/sod-conflicts).
 */
final class SodChecker
{
    public function __construct(
        private readonly GrantRepository $grants,
        private readonly SodRuleRepository $rules,
        private readonly ConnectionInterface $db,
        private readonly Clock $clock,
    ) {}

    /**
     * @param  list<string>  $extraPermissions
     * @param  list<string>  $extraRoleIds
     * @param  list<string>|null  $overridePermissions
     * @return list<array{rule_id: string, kind: string, left: string, right: string, description: string, user_id: string}>
     */
    public function conflictsFor(string $userId, array $extraPermissions = [], array $extraRoleIds = [], ?string $overrideRoleId = null, ?array $overridePermissions = null): array
    {
        $permissions = [];
        $roles = [];
        foreach ($this->grants->forUser($userId) as $g) {
            $roles[$g->roleId] = true;
            $perms = ($overrideRoleId !== null && $g->roleId === $overrideRoleId && $overridePermissions !== null) ? $overridePermissions : $g->permissions;
            foreach ($perms as $p) {
                $permissions[$p] = true;
            }
        }
        foreach ($extraPermissions as $p) {
            $permissions[$p] = true;
        }
        foreach ($extraRoleIds as $r) {
            $roles[$r] = true;
        }

        return array_map(
            static fn (SodRule $r): array => ['rule_id' => $r->id, 'kind' => $r->kind, 'left' => $r->left, 'right' => $r->right, 'description' => $r->description, 'user_id' => $userId],
            SodPolicy::violations(array_keys($permissions), array_keys($roles), $this->rules->enabled()),
        );
    }

    /**
     * Conflicts inside a permission set itself (a role bundling both sides).
     *
     * @param  list<string>  $permissions
     * @return list<array{rule_id: string, kind: string, left: string, right: string, description: string}>
     */
    public function internalConflicts(array $permissions): array
    {
        $rules = array_values(array_filter($this->rules->enabled(), static fn (SodRule $r): bool => $r->kind === SodRule::PERMISSION_PAIR));

        return array_map(
            static fn (SodRule $r): array => ['rule_id' => $r->id, 'kind' => $r->kind, 'left' => $r->left, 'right' => $r->right, 'description' => $r->description],
            SodPolicy::violations($permissions, [], $rules),
        );
    }

    /** @return list<string> users holding the role directly or by delegation (current or future-dated) */
    public function holdersOf(string $roleId): array
    {
        $now = $this->clock->now();
        /** @var list<string> $direct */
        $direct = $this->db->table('role_assignments')->where('role_id', $roleId)->whereNull('revoked_at')
            ->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>', $now))
            ->pluck('user_id')->all();
        /** @var list<string> $delegates */
        $delegates = $this->db->table('delegations as d')->join('role_assignments as a', 'a.id', '=', 'd.role_assignment_id')
            ->where('a.role_id', $roleId)->whereNull('d.revoked_at')->where('d.valid_to', '>', $now)
            ->pluck('d.delegate_id')->all();

        return array_values(array_unique(array_merge($direct, $delegates)));
    }

    /** @return list<array{rule_id: string, kind: string, left: string, right: string, description: string, user_id: string}> */
    public function standingConflicts(): array
    {
        $out = [];
        /** @var list<string> $userIds */
        $userIds = $this->db->table('users')->where('status', 'active')->orderBy('id')->pluck('id')->all();
        foreach ($userIds as $id) {
            array_push($out, ...$this->conflictsFor($id));
        }

        return $out;
    }

    /**
     * @param  list<Grant>  $grants
     * @return list<string>
     */
    public static function roleIds(array $grants): array
    {
        return array_values(array_unique(array_map(static fn (Grant $g): string => $g->roleId, $grants)));
    }
}

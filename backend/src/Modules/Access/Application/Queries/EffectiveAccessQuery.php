<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Application\Queries;

use Fundly\Modules\Access\Domain\Permission;
use Fundly\Modules\Access\Infrastructure\Persistence\GrantRepository;
use Fundly\Shared\Clock\Clock;

/**
 * "Exactly what a given user can see and do, and why" (FR-SEC-011): every
 * permission × scope with the assignment (or delegation) that grants it.
 */
final class EffectiveAccessQuery
{
    public function __construct(private readonly GrantRepository $grants, private readonly Clock $clock) {}

    /**
     * @param  list<string>|null  $tokenAbilities
     * @return array<string, mixed>
     */
    public function forUser(string $userId, ?array $tokenAbilities = null): array
    {
        $now = $this->clock->now();
        $byPermission = [];
        $assignments = [];
        foreach ($this->grants->forUser($userId) as $g) {
            $active = $g->isActiveAt($now);
            $assignments[] = [
                'assignment_id' => $g->assignmentId,
                'role_id' => $g->roleId,
                'role_code' => $g->roleCode,
                'scope' => $g->scope->toArray(),
                'valid_from' => $g->validFrom->format('Y-m-d\TH:i:s.u\Z'),
                'valid_to' => $g->validTo?->format('Y-m-d\TH:i:s.u\Z'),
                'active' => $active,
                'delegated_by' => $g->delegatedBy,
                'delegation_id' => $g->delegationId,
            ];
            if (! $active) {
                continue;
            }
            foreach ($g->permissions as $perm) {
                if ($tokenAbilities !== null && ! in_array('*', $tokenAbilities, true) && ! in_array($perm, $tokenAbilities, true)) {
                    continue;
                }
                $byPermission[$perm][] = [
                    'assignment_id' => $g->assignmentId,
                    'role_code' => $g->roleCode,
                    'scope' => $g->scope->toArray(),
                    'delegated_by' => $g->delegatedBy,
                    'valid_to' => $g->validTo?->format('Y-m-d\TH:i:s.u\Z'),
                ];
            }
        }
        ksort($byPermission);

        $permissions = [];
        foreach ($byPermission as $code => $grantedBy) {
            $permissions[] = [
                'permission' => $code,
                'sensitive' => Permission::tryFrom($code)?->isSensitive() ?? false,
                'granted_by' => $grantedBy,
            ];
        }

        return ['user_id' => $userId, 'evaluated_at' => $now->format('Y-m-d\TH:i:s.u\Z'), 'permissions' => $permissions, 'assignments' => $assignments];
    }
}

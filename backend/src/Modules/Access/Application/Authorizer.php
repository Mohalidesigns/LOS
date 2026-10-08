<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Application;

use Fundly\Modules\Access\Domain\AccessPolicy;
use Fundly\Modules\Access\Domain\Grant;
use Fundly\Modules\Access\Domain\SodPolicy;
use Fundly\Modules\Access\Domain\SodRule;
use Fundly\Modules\Access\Infrastructure\Persistence\ActionHistory;
use Fundly\Modules\Access\Infrastructure\Persistence\GrantRepository;
use Fundly\Modules\Access\Infrastructure\Persistence\SodRuleRepository;
use Fundly\Shared\Clock\Clock;
use Fundly\Shared\Security\AccessDecision;
use Fundly\Shared\Security\AccessDenied;
use Fundly\Shared\Security\AuthorizationGate;
use Fundly\Shared\Security\Principal;
use Fundly\Shared\Security\ResourceAttributes;

/**
 * Implementation of the single decision function (TRD §8.1), plus action-time
 * segregation of duties (FR-SEC-006):
 *
 *  1. standing conflict: the principal currently holds both sides of an
 *     enabled SoD rule that involves this permission → blocked;
 *  2. entity history: the principal already exercised the conflicting
 *     permission on this same entity → blocked.
 */
final class Authorizer implements AuthorizationGate
{
    public function __construct(
        private readonly GrantRepository $grants,
        private readonly SodRuleRepository $sodRules,
        private readonly ActionHistory $history,
        private readonly Clock $clock,
    ) {
    }

    public function check(Principal $principal, string $permission, ?ResourceAttributes $resource = null): AccessDecision
    {
        if ($principal->isSystem()) {
            return AccessDecision::allow($permission, null, null, 'system_identity');
        }

        return AccessPolicy::decide(
            $this->grants->forUser($principal->id),
            $permission,
            $resource,
            $this->clock->now(),
            $principal->tokenAbilities,
        );
    }

    public function authorize(Principal $principal, string $permission, ?ResourceAttributes $resource = null): AccessDecision
    {
        $decision = $this->check($principal, $permission, $resource);
        if (! $decision->allowed) {
            throw new AccessDenied($permission, $decision->reason, $resource);
        }
        if ($principal->isSystem()) {
            return $decision;
        }

        $rules = array_values(array_filter($this->sodRules->enabled(), static fn (SodRule $r): bool => $r->kind === SodRule::PERMISSION_PAIR ? $r->involvesPermission($permission) : true));
        if ($rules === []) {
            return $decision;
        }

        $active = array_values(array_filter($this->grants->forUser($principal->id), fn (Grant $g): bool => $g->isActiveAt($this->clock->now())));
        $held = [];
        foreach ($active as $grant) {
            foreach ($grant->permissions as $p) {
                $held[$p] = true;
            }
        }
        $permissions = array_keys($held);
        $rolesGrantingPermission = array_values(array_unique(array_map(static fn (Grant $g): string => $g->roleId, array_filter($active, static fn (Grant $g): bool => $g->grants($permission)))));
        $roles = array_values(array_unique(array_map(static fn (Grant $g): string => $g->roleId, $active)));

        foreach (SodPolicy::violations($permissions, $roles, $rules) as $violation) {
            $relevant = $violation->kind === SodRule::PERMISSION_PAIR
                || in_array($violation->left, $rolesGrantingPermission, true)
                || in_array($violation->right, $rolesGrantingPermission, true);
            if ($relevant) {
                throw new AccessDenied($permission, 'sod_conflict', $resource, 'Segregation of duties: you hold conflicting access ('.$violation->description.').');
            }
        }

        if ($resource?->entityType !== null && $resource->entityId !== null) {
            $counterparts = [];
            foreach ($rules as $rule) {
                if ($rule->kind === SodRule::PERMISSION_PAIR && ($c = $rule->counterpart($permission)) !== null) {
                    $counterparts[] = $c;
                }
            }
            if ($this->history->actorExercised($principal->id, $resource->entityType, $resource->entityId, $counterparts)) {
                throw new AccessDenied($permission, 'sod_conflict', $resource, 'Segregation of duties: you already performed a conflicting action on this record.');
            }
        }

        return $decision;
    }
}

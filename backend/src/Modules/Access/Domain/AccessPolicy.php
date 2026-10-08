<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Domain;

use DateTimeImmutable;
use Fundly\Shared\Security\AccessDecision;
use Fundly\Shared\Security\ResourceAttributes;

/**
 * allow(principal, permission, resource) =
 *   ∃ grant g ∈ active(principal): permission ∈ g.permissions ∧ resource ⊨ g.scope
 * (TRD §8.1). Deny by default (FR-SEC-003). A token can only narrow access.
 */
final class AccessPolicy
{
    /**
     * @param  list<Grant>  $grants
     * @param  list<string>|null  $tokenAbilities
     */
    public static function decide(array $grants, string $permission, ?ResourceAttributes $resource, DateTimeImmutable $now, ?array $tokenAbilities = null): AccessDecision
    {
        if ($tokenAbilities !== null && ! in_array('*', $tokenAbilities, true) && ! in_array($permission, $tokenAbilities, true)) {
            return AccessDecision::deny($permission, 'token_ability_missing');
        }

        $holdsAnywhere = false;
        foreach ($grants as $grant) {
            if (! $grant->isActiveAt($now) || ! $grant->grants($permission)) {
                continue;
            }
            $holdsAnywhere = true;
            if ($resource === null || self::inScope($grant, $resource)) {
                return AccessDecision::allow($permission, $grant->assignmentId, $grant->delegatedBy);
            }
        }

        return AccessDecision::deny($permission, $holdsAnywhere ? 'out_of_scope' : 'permission_not_granted');
    }

    public static function inScope(Grant $grant, ResourceAttributes $r): bool
    {
        $s = $grant->scope;

        if ($s->legalEntityIds !== [] && $r->legalEntityId !== null && ! in_array($r->legalEntityId, $s->legalEntityIds, true)) {
            return false;
        }
        if ($s->orgUnitId !== null && $r->orgUnitId !== null && ! in_array($r->orgUnitId, $grant->orgSubtree ?? [$s->orgUnitId], true)) {
            return false;
        }
        if ($s->productIds !== [] && $r->productId !== null && ! in_array($r->productId, $s->productIds, true)) {
            return false;
        }
        $currency = $r->currency ?? $r->amount?->currency->code;
        if ($s->currencies !== [] && $currency !== null && ! in_array($currency, $s->currencies, true)) {
            return false;
        }
        if ($s->maxAmount !== null && $r->amount !== null) {
            // An amount in a different currency than the cap cannot be proven within limit.
            if (! $r->amount->currency->equals($s->maxAmount->currency) || $r->amount->isGreaterThan($s->maxAmount)) {
                return false;
            }
        }
        if ($s->segments !== [] && $r->segment !== null && ! in_array($r->segment, $s->segments, true)) {
            return false;
        }
        if ($s->portfolioTags !== [] && $r->portfolioTags !== [] && array_intersect($s->portfolioTags, $r->portfolioTags) === []) {
            return false;
        }

        return true;
    }
}

<?php

declare(strict_types=1);

namespace Fundly\Shared\Security;

/**
 * The single authorisation decision function (TRD §8.1), implemented by the
 * Access module. Route middleware, the command bus and single-resource checks
 * all go through it.
 */
interface AuthorizationGate
{
    /** Does the principal hold the permission in *some* scope? (route-level gate) */
    public function check(Principal $principal, string $permission, ?ResourceAttributes $resource = null): AccessDecision;

    /**
     * Like check() but throws AccessDenied, and also applies action-time SoD
     * against the entity if one is given (FR-SEC-006).
     *
     * @throws AccessDenied
     */
    public function authorize(Principal $principal, string $permission, ?ResourceAttributes $resource = null): AccessDecision;
}

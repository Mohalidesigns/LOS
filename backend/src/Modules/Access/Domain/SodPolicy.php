<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Domain;

/**
 * Segregation of duties (FR-SEC-006). Evaluated over a user's *combined*
 * holdings (all active and future assignments, plus delegated ones), so a
 * conflict cannot be assembled from several individually harmless grants.
 */
final class SodPolicy
{
    /**
     * @param  list<string>  $permissions  union of permissions held
     * @param  list<string>  $roleIds  roles held
     * @param  list<SodRule>  $rules
     * @return list<SodRule> violated rules
     */
    public static function violations(array $permissions, array $roleIds, array $rules): array
    {
        $p = array_flip($permissions);
        $r = array_flip($roleIds);
        $out = [];
        foreach ($rules as $rule) {
            $held = $rule->kind === SodRule::PERMISSION_PAIR ? $p : $r;
            if (isset($held[$rule->left], $held[$rule->right])) {
                $out[] = $rule;
            }
        }

        return $out;
    }
}

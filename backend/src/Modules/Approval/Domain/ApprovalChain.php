<?php

declare(strict_types=1);

namespace Fundly\Modules\Approval\Domain;

use Closure;

/**
 * Builds the ordered chain of authority levels for one request (TRD §6.3):
 * the routing row's levels, then escalation (FR-APV-007):
 *   - an exception at or above the configured severity adds the next level
 *     above the highest required one;
 *   - a level nobody is eligible to approve is replaced by the next level up.
 * Levels are sequential in P1: each must approve before the next votes.
 */
final class ApprovalChain
{
    /**
     * @param  list<string>  $routed  level codes from the routing row
     * @param  Closure(array{code: string, name: string, roles: list<string>, mode: string}): bool  $staffed  is anyone eligible at this level?
     * @return list<array{code: string, name: string, roles: list<string>, mode: string, added_by: string, note: ?string}>
     */
    public static function build(MatrixDefinition $matrix, array $routed, bool $exceptionEscalates, Closure $staffed): array
    {
        $all = $matrix->levels();
        $index = array_flip(array_column($all, 'code'));
        $chosen = [];
        foreach (array_unique($routed) as $code) {
            if (isset($index[$code])) {
                $chosen[$index[$code]] = ['added_by' => 'matrix', 'note' => null];
            }
        }
        ksort($chosen);
        if ($exceptionEscalates && $chosen !== []) {
            $top = (int) array_key_last($chosen);
            if (isset($all[$top + 1])) {
                $chosen[$top + 1] = ['added_by' => 'exception', 'note' => 'Policy exception on the credit decision: escalated above '.$all[$top]['code'].'.'];
            }
        }
        if ($matrix->escalatesWhenUnstaffed()) {
            ksort($chosen);
            foreach (array_keys($chosen) as $i) {
                $j = $i;
                while (isset($chosen[$j]) && ! $staffed($all[$j]) && isset($all[$j + 1])) {
                    $from = $all[$j]['code'];
                    unset($chosen[$j]);
                    $j++;
                    $chosen[$j] ??= ['added_by' => 'no_eligible_approver', 'note' => "No eligible approver at {$from}: escalated to {$all[$j]['code']}."];
                }
            }
        }
        ksort($chosen);
        $out = [];
        foreach ($chosen as $i => $meta) {
            $out[] = $all[$i] + $meta;
        }

        return $out;
    }
}

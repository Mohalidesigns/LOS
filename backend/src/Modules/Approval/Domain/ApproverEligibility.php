<?php

declare(strict_types=1);

namespace Fundly\Modules\Approval\Domain;

use Fundly\Shared\Money\Money;

/**
 * Can this person vote at this level (TRD §6.3 step 2)? They need a grant
 * whose role is eligible at the level, an effective limit covering the
 * exposure, and they must pass approver SoD (FR-APV-005): not the
 * originator, not the recommender, and not someone who already voted on
 * this request at a lower level.
 */
final class ApproverEligibility
{
    /**
     * @param  list<string>  $levelRoles
     * @param  list<string>  $priorVoters
     * @param  list<array{assignment_id: string, role_code: string, role_name: string, delegation_id: ?string, delegated_by: ?string, limit: ?Money, limit_source: ?string, limit_components: list<array<string, mixed>>}>  $authorities
     * @return array{eligible: bool, blockers: list<string>, basis: ?array<string, mixed>}
     */
    public static function assess(string $voterId, string $originatorId, ?string $recommenderId, array $priorVoters, array $levelRoles, array $authorities, Money $exposure): array
    {
        $blockers = [];
        if ($voterId === $originatorId) {
            $blockers[] = 'You originated this application, so you cannot approve it (FR-APV-005).';
        }
        if ($recommenderId !== null && $voterId === $recommenderId) {
            $blockers[] = 'You recommended this application, so you cannot approve it (FR-APV-005).';
        }
        if (in_array($voterId, $priorVoters, true)) {
            $blockers[] = 'You already voted on this application at a lower level (FR-APV-005).';
        }
        $atLevel = array_values(array_filter($authorities, static fn (array $a): bool => in_array($a['role_code'], $levelRoles, true)));
        $basis = null;
        if ($atLevel === []) {
            $blockers[] = 'None of your roles is eligible at this authority level ('.implode(', ', $levelRoles).').';
        } else {
            $covering = array_values(array_filter($atLevel, static fn (array $a): bool => $a['limit'] !== null
                && $a['limit']->currency->code === $exposure->currency->code && ! $exposure->isGreaterThan($a['limit'])));
            if ($covering === []) {
                $best = self::best($atLevel);
                $blockers[] = $best['limit'] === null
                    ? 'No approval limit is configured for you at this level, so you have no authority (FR-APV-002).'
                    : sprintf('Your effective approval limit (%s %s, from %s) is below the exposure of %s %s (FR-APV-002).', $best['limit']->currency->code, (string) $best['limit']->amount, (string) $best['limit_source'], $exposure->currency->code, (string) $exposure->amount);
            } else {
                // own authority before delegated, then the highest limit
                usort($covering, static function (array $a, array $b): int {
                    $own = ($a['delegation_id'] !== null) <=> ($b['delegation_id'] !== null);

                    return $own !== 0 ? $own : $b['limit']->compareTo($a['limit']);
                });
                $basis = self::basis($covering[0]);
            }
        }

        return ['eligible' => $blockers === [], 'blockers' => $blockers, 'basis' => $blockers === [] ? $basis : null];
    }

    /**
     * @param  non-empty-list<array<string, mixed>>  $authorities
     * @return array<string, mixed>
     */
    private static function best(array $authorities): array
    {
        $best = $authorities[0];
        foreach ($authorities as $a) {
            if ($a['limit'] !== null && ($best['limit'] === null || $a['limit']->compareTo($best['limit']) > 0)) {
                $best = $a;
            }
        }

        return $best;
    }

    /**
     * @param  array<string, mixed>  $a
     * @return array<string, mixed>
     */
    private static function basis(array $a): array
    {
        return [
            'assignment_id' => $a['assignment_id'],
            'role_code' => $a['role_code'],
            'role_name' => $a['role_name'],
            'delegation_id' => $a['delegation_id'],
            'delegated_by' => $a['delegated_by'],
            'effective_limit' => $a['limit']->toArray(),
            'limit_source' => $a['limit_source'],
            'limit_components' => $a['limit_components'],
        ];
    }
}

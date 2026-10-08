<?php

declare(strict_types=1);

namespace Fundly\Modules\Party\Domain;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Effective (look-through) ownership for layered structures (FR-CUS-006):
 * a person holding 50% of a company that holds 40% of the applicant owns 20%.
 * Pure: the caller supplies the ownership edges.
 */
final class OwnershipCalculator
{
    /**
     * @param  array<string, list<array{owner: string, percent: string}>>  $edges  company id → its direct owners
     * @param  array<string, string>  $types  party id → party type
     * @return array<string, string> individual party id → effective percent (4 dp)
     */
    public static function effective(string $companyId, array $edges, array $types, int $maxDepth = 10): array
    {
        $totals = [];
        self::walk($companyId, BigDecimal::of(100), $edges, $types, $totals, [$companyId => true], $maxDepth);
        $out = [];
        foreach ($totals as $id => $pct) {
            $out[$id] = (string) $pct->toScale(4, RoundingMode::HalfEven);
        }
        arsort($out, SORT_NUMERIC);

        return $out;
    }

    /**
     * @param  array<string, list<array{owner: string, percent: string}>>  $edges
     * @param  array<string, string>  $types
     * @param  array<string, BigDecimal>  $totals
     * @param  array<string, true>  $path
     */
    private static function walk(string $company, BigDecimal $share, array $edges, array $types, array &$totals, array $path, int $depth): void
    {
        if ($depth === 0) {
            return;
        }
        foreach ($edges[$company] ?? [] as $edge) {
            $portion = $share->multipliedBy(BigDecimal::of($edge['percent']))->dividedBy(100, 8, RoundingMode::HalfEven);
            $owner = $edge['owner'];
            if (($types[$owner] ?? null) === PartyType::LimitedCompany->value) {
                if (! isset($path[$owner])) {
                    self::walk($owner, $portion, $edges, $types, $totals, $path + [$owner => true], $depth - 1);
                }

                continue;
            }
            $totals[$owner] = ($totals[$owner] ?? BigDecimal::zero())->plus($portion);
        }
    }
}

<?php

declare(strict_types=1);

namespace Fundly\Modules\Disbursement\Domain;

use Brick\Math\BigDecimal;

/**
 * Booking-time comparison of the CBA schedule with the accepted offer's
 * (LOS-FR-313, FR-OFR-003). Instalments are matched by number; due dates
 * move with the value date, so amounts are compared, not dates. Any
 * instalment whose principal, interest or total differs by more than the
 * tolerance, or a different instalment count, is a variance that blocks
 * booking as an exception.
 */
final class ScheduleVariance
{
    /**
     * @param  list<array{number: int, principal: string, interest: string, total: string}>  $offered
     * @param  list<array{number: int, principal: string, interest: string, total: string}>  $booked
     * @return array{within_tolerance: bool, tolerance: string, max_difference: string, offered_count: int, booked_count: int, mismatches: list<array{number: int, field: string, offered: ?string, booked: ?string, difference: string}>}
     */
    public static function compare(array $offered, array $booked, string $tolerance): array
    {
        $tol = BigDecimal::of($tolerance);
        $byNumber = [];
        foreach ($booked as $b) {
            $byNumber[$b['number']] = $b;
        }
        $max = BigDecimal::zero();
        $mismatches = [];
        foreach ($offered as $o) {
            $b = $byNumber[$o['number']] ?? null;
            if ($b === null) {
                $mismatches[] = ['number' => $o['number'], 'field' => 'instalment', 'offered' => $o['total'], 'booked' => null, 'difference' => $o['total']];

                continue;
            }
            foreach (['principal', 'interest', 'total'] as $field) {
                $diff = BigDecimal::of($o[$field])->minus(BigDecimal::of($b[$field]))->abs();
                if ($diff->isGreaterThan($max)) {
                    $max = $diff;
                }
                if ($diff->isGreaterThan($tol)) {
                    $mismatches[] = ['number' => $o['number'], 'field' => $field, 'offered' => $o[$field], 'booked' => $b[$field], 'difference' => (string) $diff];
                }
            }
        }
        if (count($booked) !== count($offered)) {
            $mismatches[] = ['number' => 0, 'field' => 'instalment_count', 'offered' => (string) count($offered), 'booked' => (string) count($booked), 'difference' => (string) abs(count($booked) - count($offered))];
        }

        return [
            'within_tolerance' => $mismatches === [],
            'tolerance' => (string) $tol,
            'max_difference' => (string) $max,
            'offered_count' => count($offered),
            'booked_count' => count($booked),
            'mismatches' => $mismatches,
        ];
    }
}

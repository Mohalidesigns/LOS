<?php

declare(strict_types=1);

namespace Fundly\Modules\Disbursement\Domain;

use Brick\Math\BigDecimal;

/**
 * Fees taken at disbursement (FR-DSB-004): the upfront fees disclosed in the
 * accepted offer's key facts are deducted, so the customer receives the net
 * amount and the loan account carries the full principal.
 */
final class FeeSplit
{
    /**
     * @param  list<array{code: string, name: string, type: string, amount: string, costed: bool}>  $fees
     * @return array{deducted: list<array{code: string, name: string, amount: string}>, total: string, net: string}
     */
    public static function split(string $principal, array $fees): array
    {
        $deducted = [];
        $total = BigDecimal::zero();
        foreach ($fees as $f) {
            if ($f['type'] !== 'upfront' || ! $f['costed']) {
                continue;
            }
            $deducted[] = ['code' => $f['code'], 'name' => $f['name'], 'amount' => $f['amount']];
            $total = $total->plus(BigDecimal::of($f['amount']));
        }

        return ['deducted' => $deducted, 'total' => (string) $total, 'net' => (string) BigDecimal::of($principal)->minus($total)];
    }
}

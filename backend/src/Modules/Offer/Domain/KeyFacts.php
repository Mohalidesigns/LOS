<?php

declare(strict_types=1);

namespace Fundly\Modules\Offer\Domain;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Key-facts / cost-of-credit statement (FR-OFR-002, FR-CMP-025), method
 * `ng_v0` [verify against the CBN consumer protection disclosure rules, G-24]:
 *
 *   total interest       Σ scheduled interest
 *   fees                 every non-contingent product fee on the principal
 *                        (percent or flat); contingent fees are listed, not costed
 *   total cost of credit total interest + fees
 *   net disbursed        principal − upfront fees (deducted at disbursement)
 *   APR                  12 × the monthly rate i solving
 *                        net disbursed = Σ instalment_k / (1 + i)^k
 *   effective rate       (1 + i)^12 − 1
 *
 * All arithmetic is exact decimal; the rate is found by bisection to 1e-10.
 */
final class KeyFacts
{
    public const METHOD = 'ng_v0';

    private const SCALE = 20;

    /**
     * @param  int<0, max>  $minorUnits
     * @param  list<array{total: string, interest: string}>  $instalments  scheduled amounts, in order, one per month
     * @param  list<array<string, mixed>>  $fees  product fee definitions {code, name, type, calc, value}
     * @return array{method: string, principal: string, total_interest: string, fees: list<array{code: string, name: string, type: string, amount: string, costed: bool}>, total_fees: string, total_cost_of_credit: string, total_repayable: string, net_disbursed: string, apr_percent: string, effective_annual_rate_percent: string, instalment_count: int}
     */
    public static function compute(string $principal, int $minorUnits, array $instalments, array $fees): array
    {
        $p = BigDecimal::of($principal);
        $interest = BigDecimal::zero();
        $repayable = BigDecimal::zero();
        foreach ($instalments as $i) {
            $interest = $interest->plus(BigDecimal::of($i['interest']));
            $repayable = $repayable->plus(BigDecimal::of($i['total']));
        }
        $feeRows = [];
        $costed = BigDecimal::zero();
        $upfront = BigDecimal::zero();
        foreach ($fees as $f) {
            $value = BigDecimal::of((string) ($f['value'] ?? '0'));
            $amount = ($f['calc'] ?? 'flat') === 'percent' ? $p->multipliedBy($value)->dividedBy(100, $minorUnits, RoundingMode::HalfEven) : $value->toScale($minorUnits, RoundingMode::HalfEven);
            $type = (string) ($f['type'] ?? 'upfront');
            $isCosted = $type !== 'contingent';
            if ($isCosted) {
                $costed = $costed->plus($amount);
            }
            if ($type === 'upfront') {
                $upfront = $upfront->plus($amount);
            }
            $feeRows[] = ['code' => (string) ($f['code'] ?? ''), 'name' => (string) ($f['name'] ?? ''), 'type' => $type, 'amount' => (string) $amount, 'costed' => $isCosted];
        }
        $net = $p->minus($upfront);
        $monthly = self::monthlyRate($net, array_map(static fn (array $i): BigDecimal => BigDecimal::of($i['total']), $instalments));
        $growth = BigDecimal::one();
        for ($k = 0; $k < 12; $k++) {
            $growth = $growth->multipliedBy($monthly->plus(1))->toScale(self::SCALE, RoundingMode::HalfEven);
        }
        $fmt = static fn (BigDecimal $d): string => (string) $d->toScale($minorUnits, RoundingMode::HalfEven);

        return [
            'method' => self::METHOD,
            'principal' => $fmt($p),
            'total_interest' => $fmt($interest),
            'fees' => $feeRows,
            'total_fees' => $fmt($costed),
            'total_cost_of_credit' => $fmt($interest->plus($costed)),
            'total_repayable' => $fmt($repayable),
            'net_disbursed' => $fmt($net),
            'apr_percent' => (string) $monthly->multipliedBy(1200)->toScale(2, RoundingMode::HalfEven),
            'effective_annual_rate_percent' => (string) $growth->minus(1)->multipliedBy(100)->toScale(2, RoundingMode::HalfEven),
            'instalment_count' => count($instalments),
        ];
    }

    /** @param list<BigDecimal> $flows */
    private static function monthlyRate(BigDecimal $net, array $flows): BigDecimal
    {
        if ($flows === [] || ! $net->isPositive()) {
            return BigDecimal::zero();
        }
        $lo = BigDecimal::zero();
        $hi = BigDecimal::one();
        $epsilon = BigDecimal::of('0.0000000001');
        while ($hi->minus($lo)->isGreaterThan($epsilon)) {
            $mid = $lo->plus($hi)->dividedBy(2, self::SCALE, RoundingMode::HalfEven);
            // present value falls as the rate rises: too high a PV means the rate is too low
            if (self::presentValue($mid, $flows)->isGreaterThan($net)) {
                $lo = $mid;
            } else {
                $hi = $mid;
            }
        }

        return $lo->plus($hi)->dividedBy(2, self::SCALE, RoundingMode::HalfEven);
    }

    /** @param list<BigDecimal> $flows */
    private static function presentValue(BigDecimal $rate, array $flows): BigDecimal
    {
        $factor = BigDecimal::one();
        $pv = BigDecimal::zero();
        $step = $rate->plus(1);
        foreach ($flows as $flow) {
            $factor = $factor->multipliedBy($step)->toScale(self::SCALE, RoundingMode::HalfEven);
            $pv = $pv->plus($flow->dividedBy($factor, self::SCALE, RoundingMode::HalfEven));
        }

        return $pv;
    }
}

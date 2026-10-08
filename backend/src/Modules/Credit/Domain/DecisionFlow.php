<?php

declare(strict_types=1);

namespace Fundly\Modules\Credit\Domain;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Fundly\Shared\Rules\DecisionTable;
use Fundly\Shared\Rules\EvaluatorRegistry;

/**
 * The fixed decision flow of TRD §7.1:
 *   formulas → knock-out → policy → grade → affordability → pricing → outcome.
 * Pure and deterministic: given the same rule set, facts and evaluator
 * version it returns the same outputs, which is what replay checks.
 *
 * Outcomes: approve | refer | counter_offer. P1 never auto-declines (D-038b):
 * knock-outs and policy hits refer to a human.
 */
final class DecisionFlow
{
    /**
     * @param  array<string, mixed>  $ruleSet  validated `credit.rule_set` content
     * @param  array<string, mixed>  $facts  assembled facts (decimal strings)
     * @return array{outcome: string, risk_grade: string, reason_codes: list<array{code: string, text_key: string, stage: string}>, requested_terms: array<string, mixed>, recommended_terms: array<string, mixed>, affordability: array<string, mixed>, trace: list<array<string, mixed>>, facts: array<string, mixed>}
     */
    public static function run(array $ruleSet, array $facts, string $evaluatorVersion): array
    {
        $trace = [];
        $reasons = [];
        $facts['formulas'] = [];
        foreach ((array) ($ruleSet['formulas'] ?? []) as $f) {
            if (! is_array($f) || ! is_string($f['name'] ?? null) || ! is_string($f['expression'] ?? null)) {
                continue;
            }
            $value = EvaluatorRegistry::for($evaluatorVersion, $facts)->evaluate($f['expression']);
            $facts['formulas'][$f['name']] = $value instanceof BigDecimal ? (string) $value->toScale(4, RoundingMode::HalfEven) : $value;
            $trace[] = ['stage' => 'formula', 'name' => $f['name'], 'expression' => $f['expression'], 'value' => $facts['formulas'][$f['name']]];
        }

        foreach (['knockouts' => 'knockout', 'policy' => 'policy'] as $table => $stage) {
            if (! is_array($ruleSet[$table] ?? null)) {
                continue;
            }
            $r = DecisionTable::evaluate(['hit_policy' => 'COLLECT'] + $ruleSet[$table], EvaluatorRegistry::for($evaluatorVersion, $facts));
            $trace[] = ['stage' => $stage, 'rows' => $r['trace']];
            foreach ($r['matched'] as $m) {
                if ($m['reason'] !== null) {
                    $reasons[] = $m['reason'] + ['stage' => $stage];
                }
            }
        }
        $referred = $reasons !== [];

        $grade = 'UNGRADED';
        if (is_array($ruleSet['grade'] ?? null)) {
            $r = DecisionTable::evaluate(['hit_policy' => 'FIRST'] + $ruleSet['grade'], EvaluatorRegistry::for($evaluatorVersion, $facts));
            $trace[] = ['stage' => 'grade', 'rows' => $r['trace']];
            if (isset($r['matched'][0])) {
                $grade = is_string($r['matched'][0]['outputs']['risk_grade'] ?? null) ? $r['matched'][0]['outputs']['risk_grade'] : $grade;
                if ($r['matched'][0]['reason'] !== null) {
                    $reasons[] = $r['matched'][0]['reason'] + ['stage' => 'grade'];
                }
            }
        }
        $facts['decision'] = ['risk_grade' => $grade];

        $amount = self::dec($facts['facility']['amount'] ?? null) ?? BigDecimal::zero();
        $tenor = (int) ($facts['facility']['tenor_months'] ?? 0);
        $productRate = self::dec($facts['facility']['rate_percent'] ?? null) ?? BigDecimal::zero();
        $currency = (string) ($facts['application']['currency'] ?? 'NGN');

        $afford = ['monthly_income' => null, 'existing_obligations' => null, 'new_instalment' => null, 'dsr_percent' => null, 'max_dsr_percent' => null, 'passed' => null, 'max_affordable_amount' => null];
        if (is_array($ruleSet['affordability'] ?? null)) {
            $a = $ruleSet['affordability'];
            $max = BigDecimal::of((string) $a['max_dsr_percent']);
            $income = self::dec(EvaluatorRegistry::for($evaluatorVersion, $facts)->evaluate((string) $a['income_fact']));
            $obligations = self::dec($facts['bureau']['monthly_obligations'] ?? null) ?? BigDecimal::zero();
            $dsr = self::dec($facts['formulas'][(string) $a['dsr_formula']] ?? null);
            $afford['max_dsr_percent'] = (string) $max;
            $afford['monthly_income'] = $income === null ? null : self::money($income, $currency);
            $afford['existing_obligations'] = self::money($obligations, $currency);
            $afford['new_instalment'] = ($i = self::pmt($productRate, $tenor, $amount)) === null ? null : self::money($i, $currency);
            $afford['dsr_percent'] = $dsr === null ? null : (string) $dsr->toScale(2, RoundingMode::HalfEven);
            $afford['passed'] = $dsr === null ? null : ! $dsr->isGreaterThan($max);
            if ($income !== null && ($unit = self::pmt($productRate, $tenor, BigDecimal::one())) !== null && $unit->isPositive()) {
                $room = $income->multipliedBy($max)->dividedBy(100, 4, RoundingMode::HalfEven)->minus($obligations);
                $principal = $room->isPositive() ? $room->dividedBy($unit, 0, RoundingMode::Down) : BigDecimal::zero();
                // round down to the nearest 10,000 for a clean counter-offer figure
                $principal = $principal->dividedBy(10000, 0, RoundingMode::Down)->multipliedBy(10000);
                $afford['max_affordable_amount'] = self::money(BigDecimal::min($principal, $amount), $currency);
            }
            $trace[] = ['stage' => 'affordability', 'dsr_percent' => $afford['dsr_percent'], 'max_dsr_percent' => $afford['max_dsr_percent'], 'passed' => $afford['passed']];
        }

        $rate = $productRate;
        if (is_array($ruleSet['pricing'] ?? null)) {
            $r = DecisionTable::evaluate(['hit_policy' => 'FIRST'] + $ruleSet['pricing'], EvaluatorRegistry::for($evaluatorVersion, $facts));
            $trace[] = ['stage' => 'pricing', 'rows' => $r['trace']];
            $priced = self::dec($r['matched'][0]['outputs']['rate_percent'] ?? null);
            $rate = $priced ?? $rate;
        }

        $recommendedAmount = $amount;
        if ($referred) {
            $outcome = 'refer';
            $reasons[] = ['code' => 'OUT_REFER', 'text_key' => 'reason.out_refer', 'stage' => 'outcome'];
        } elseif ($afford['passed'] === null && is_array($ruleSet['affordability'] ?? null)) {
            $outcome = 'refer';
            $reasons[] = ['code' => 'AFF_DATA_MISSING', 'text_key' => 'reason.aff_data_missing', 'stage' => 'affordability'];
        } elseif ($afford['passed'] === false) {
            $reasons[] = ['code' => 'AFF_DSR_EXCEEDED', 'text_key' => 'reason.aff_dsr_exceeded', 'stage' => 'affordability'];
            $maxAmount = self::dec($afford['max_affordable_amount']['amount'] ?? null);
            $minAmount = self::dec($facts['product']['min_amount'] ?? null) ?? BigDecimal::zero();
            if ($maxAmount !== null && $maxAmount->isPositive() && ! $maxAmount->isLessThan($minAmount)) {
                $outcome = 'counter_offer';
                $recommendedAmount = $maxAmount;
                $reasons[] = ['code' => 'OUT_COUNTER_OFFER', 'text_key' => 'reason.out_counter_offer', 'stage' => 'outcome'];
            } else {
                $outcome = 'refer';
                $reasons[] = ['code' => 'OUT_REFER', 'text_key' => 'reason.out_refer', 'stage' => 'outcome'];
            }
        } else {
            $outcome = 'approve';
            $reasons[] = ['code' => 'OUT_APPROVE', 'text_key' => 'reason.out_approve', 'stage' => 'outcome'];
        }
        $trace[] = ['stage' => 'outcome', 'outcome' => $outcome, 'risk_grade' => $grade, 'rate_percent' => (string) $rate];

        return [
            'outcome' => $outcome,
            'risk_grade' => $grade,
            'reason_codes' => $reasons,
            'requested_terms' => self::terms($amount, $tenor, $productRate, $currency),
            'recommended_terms' => self::terms($recommendedAmount, $tenor, $rate, $currency),
            'affordability' => $afford,
            'trace' => $trace,
            'facts' => $facts,
        ];
    }

    /** @return array{amount: array{amount: string, currency: string}, tenor_months: int, rate_percent: string, monthly_instalment: array{amount: string, currency: string}|null} */
    private static function terms(BigDecimal $amount, int $tenor, BigDecimal $rate, string $currency): array
    {
        $i = self::pmt($rate, $tenor, $amount);

        return ['amount' => self::money($amount, $currency), 'tenor_months' => $tenor, 'rate_percent' => (string) $rate->toScale(4, RoundingMode::HalfEven), 'monthly_instalment' => $i === null ? null : self::money($i, $currency)];
    }

    /** Monthly annuity instalment for an annual percentage rate. */
    private static function pmt(BigDecimal $annualPercent, int $months, BigDecimal $principal): ?BigDecimal
    {
        if ($months <= 0) {
            return null;
        }
        $r = $annualPercent->dividedBy(1200, 12, RoundingMode::HalfEven);
        if ($r->isZero()) {
            return $principal->dividedBy($months, 4, RoundingMode::HalfEven);
        }
        $g = BigDecimal::one()->plus($r)->power($months);

        return $principal->multipliedBy($r)->multipliedBy($g)->dividedBy($g->minus(1), 4, RoundingMode::HalfEven);
    }

    /** @return array{amount: string, currency: string} */
    private static function money(BigDecimal $v, string $currency): array
    {
        return ['amount' => (string) $v->toScale(4, RoundingMode::HalfEven), 'currency' => $currency];
    }

    private static function dec(mixed $v): ?BigDecimal
    {
        if ($v instanceof BigDecimal) {
            return $v;
        }
        if (is_int($v) || (is_string($v) && preg_match('/^-?\d+(\.\d+)?$/', $v) === 1)) {
            return BigDecimal::of($v);
        }

        return null;
    }
}

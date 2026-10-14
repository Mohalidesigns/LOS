<?php

declare(strict_types=1);

namespace Fundly\Modules\Collateral\Domain;

use Brick\Math\BigDecimal;
use Fundly\Shared\Exceptions\ValidationFailed;

/**
 * A collateral type (FR-COL-001/002/004): its category, haircut, valuation
 * and insurance requirements, and the perfection checklist, which can
 * differ by state (G-34). Built-in defaults ship for every BRD type; a
 * tenant overrides one with a `collateral.type` configuration version whose
 * key is the type code, activated through maker-checker.
 *
 *   { name, category, haircut_percent: "30", requires_valuation, valuation_validity_months?,
 *     requires_insurance, perfection: { default: [ {code, name, timing, due_days?} ], by_state?: { LA: [...] } } }
 */
final class CollateralType
{
    public const CATEGORIES = ['real_estate', 'vehicle', 'equipment', 'inventory', 'receivables', 'cash_deposit', 'securities', 'guarantee', 'insurance_assignment'];

    public const TIMINGS = ['precedent', 'subsequent'];

    /** @param array<string, mixed> $content */
    private function __construct(private readonly array $content) {}

    /** @param array<string, mixed> $content */
    public static function of(array $content): self
    {
        return new self($content);
    }

    /** @param array<string, mixed> $content */
    public static function validate(array $content): void
    {
        $e = [];
        if (! is_string($content['name'] ?? null) || trim($content['name']) === '' || mb_strlen($content['name']) > 120) {
            $e['content.name'][] = 'name is required (max 120 characters).';
        }
        if (! in_array($content['category'] ?? null, self::CATEGORIES, true)) {
            $e['content.category'][] = 'category must be one of: '.implode(', ', self::CATEGORIES).'.';
        }
        $haircut = $content['haircut_percent'] ?? null;
        if (! is_string($haircut) || preg_match('/^\d{1,3}(\.\d{1,4})?$/', $haircut) !== 1 || BigDecimal::of($haircut)->isGreaterThan(100)) {
            $e['content.haircut_percent'][] = 'haircut_percent must be a decimal string between 0 and 100.';
        }
        foreach (['requires_valuation', 'requires_insurance'] as $flag) {
            if (! is_bool($content[$flag] ?? null)) {
                $e["content.{$flag}"][] = "{$flag} must be true or false.";
            }
        }
        $months = $content['valuation_validity_months'] ?? null;
        if ($months !== null && (! is_int($months) || $months < 1 || $months > 120)) {
            $e['content.valuation_validity_months'][] = 'valuation_validity_months must be an integer between 1 and 120.';
        }
        $perfection = $content['perfection'] ?? null;
        if (! is_array($perfection) || ! is_array($perfection['default'] ?? null)) {
            $e['content.perfection.default'][] = 'perfection.default must list the perfection steps (it may be empty).';
            $perfection = ['default' => []];
        }
        self::steps($perfection['default'], 'content.perfection.default', $e);
        $states = $perfection['by_state'] ?? [];
        if (! is_array($states)) {
            $e['content.perfection.by_state'][] = 'by_state must map state codes to step lists.';
            $states = [];
        }
        foreach ($states as $state => $steps) {
            if (preg_match('/^[A-Z]{2}$/', (string) $state) !== 1) {
                $e["content.perfection.by_state.{$state}"][] = 'State codes are two upper-case letters (e.g. LA, FC).';
            }
            self::steps($steps, "content.perfection.by_state.{$state}", $e);
        }
        if ($e !== []) {
            throw ValidationFailed::with($e);
        }
    }

    public function name(): string
    {
        return (string) $this->content['name'];
    }

    public function category(): string
    {
        return (string) $this->content['category'];
    }

    public function haircutPercent(): BigDecimal
    {
        return BigDecimal::of((string) $this->content['haircut_percent']);
    }

    public function requiresValuation(): bool
    {
        return (bool) $this->content['requires_valuation'];
    }

    public function requiresInsurance(): bool
    {
        return (bool) $this->content['requires_insurance'];
    }

    public function valuationValidityMonths(): ?int
    {
        $m = $this->content['valuation_validity_months'] ?? null;

        return is_int($m) ? $m : null;
    }

    /**
     * The checklist for an asset in this state (the state's own list when one is configured).
     *
     * @return list<array{code: string, name: string, timing: string, due_days: ?int}>
     */
    public function perfectionSteps(?string $state): array
    {
        $p = is_array($this->content['perfection'] ?? null) ? $this->content['perfection'] : [];
        $byState = is_array($p['by_state'] ?? null) ? $p['by_state'] : [];
        $steps = $state !== null && is_array($byState[$state] ?? null) ? $byState[$state] : (is_array($p['default'] ?? null) ? $p['default'] : []);
        $out = [];
        foreach ($steps as $s) {
            if (is_array($s)) {
                $out[] = ['code' => (string) ($s['code'] ?? ''), 'name' => (string) ($s['name'] ?? ''), 'timing' => (string) ($s['timing'] ?? 'precedent'), 'due_days' => is_int($s['due_days'] ?? null) ? $s['due_days'] : null];
            }
        }

        return $out;
    }

    /** @param array<string, list<string>> $e */
    private static function steps(mixed $steps, string $path, array &$e): void
    {
        if (! is_array($steps) || ! array_is_list($steps)) {
            $e[$path][] = 'Give a list of steps.';

            return;
        }
        $seen = [];
        foreach ($steps as $i => $s) {
            $p = "{$path}.{$i}";
            $code = is_array($s) ? ($s['code'] ?? null) : null;
            if (! is_string($code) || preg_match('/^[a-z][a-z0-9_]{1,47}$/', $code) !== 1) {
                $e["{$p}.code"][] = 'code must be lower-case snake_case.';
            } elseif (isset($seen[$code])) {
                $e["{$p}.code"][] = 'Step codes must be unique.';
            } else {
                $seen[$code] = true;
            }
            if (! is_array($s) || ! is_string($s['name'] ?? null) || trim($s['name']) === '') {
                $e["{$p}.name"][] = 'name is required.';
            }
            if (! is_array($s) || ! in_array($s['timing'] ?? null, self::TIMINGS, true)) {
                $e["{$p}.timing"][] = 'timing must be precedent (blocks disbursement) or subsequent (tracked after booking).';
            }
            $due = is_array($s) ? ($s['due_days'] ?? null) : null;
            if ($due !== null && (! is_int($due) || $due < 0 || $due > 730)) {
                $e["{$p}.due_days"][] = 'due_days must be an integer between 0 and 730.';
            }
        }
    }
}

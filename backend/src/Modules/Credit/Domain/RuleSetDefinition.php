<?php

declare(strict_types=1);

namespace Fundly\Modules\Credit\Domain;

use Brick\Math\BigDecimal;
use Fundly\Shared\Exceptions\ValidationFailed;
use Fundly\Shared\Rules\DecisionTable;
use Fundly\Shared\Rules\EvaluatorRegistry;
use Fundly\Shared\Rules\RuleError;

/**
 * Validates a `credit.rule_set` configuration version (FR-CRD-001/002,
 * FR-CFG-002). Expressions must parse, use only whitelisted functions and
 * reference known fact roots. The flow is fixed (knock-out → policy → grade
 * → affordability → pricing → outcome); rows cannot set the outcome, which
 * is how D-038(b) "never auto-decline in P1" is guaranteed.
 */
final class RuleSetDefinition
{
    public const FACT_ROOTS = ['application', 'applicant', 'product', 'facility', 'bureau', 'formulas', 'decision'];

    public const TABLES = ['knockouts' => 'COLLECT', 'policy' => 'COLLECT', 'grade' => 'FIRST', 'pricing' => 'FIRST'];

    /** @var array<string, list<string>> */
    private array $errors = [];

    /** @param array<string, mixed> $content */
    public static function validate(array $content): void
    {
        $v = new self;
        $v->check($content);
        if ($v->errors !== []) {
            throw new ValidationFailed($v->errors);
        }
    }

    /** @param array<string, mixed> $c */
    private function check(array $c): void
    {
        $version = $c['evaluator_version'] ?? null;
        if (! is_string($version) || ! EvaluatorRegistry::supports($version)) {
            $this->fail('evaluator_version', 'evaluator_version must be one of: '.implode(', ', EvaluatorRegistry::versions()).'.');
        }
        $names = [];
        foreach ((array) ($c['formulas'] ?? []) as $i => $f) {
            $name = is_array($f) ? ($f['name'] ?? null) : null;
            if (! is_string($name) || preg_match('/^[a-z][a-z0-9_]{0,47}$/', $name) !== 1 || in_array($name, $names, true)) {
                $this->fail("formulas.{$i}.name", 'Formula names must be unique snake_case identifiers.');
            } else {
                $names[] = $name;
            }
            $this->expression("formulas.{$i}.expression", is_array($f) ? ($f['expression'] ?? null) : null);
        }
        foreach (self::TABLES as $table => $defaultPolicy) {
            if (! isset($c[$table])) {
                continue;
            }
            $t = $c[$table];
            if (! is_array($t) || ! is_array($t['rows'] ?? null)) {
                $this->fail($table, "{$table} must be a decision table with rows.");

                continue;
            }
            if (isset($t['hit_policy']) && ! in_array($t['hit_policy'], DecisionTable::HIT_POLICIES, true)) {
                $this->fail("{$table}.hit_policy", 'hit_policy must be one of: '.implode(', ', DecisionTable::HIT_POLICIES).'.');
            }
            foreach ($t['rows'] as $i => $row) {
                $p = "{$table}.rows.{$i}";
                if (! is_array($row)) {
                    $this->fail($p, 'Each row must be an object.');

                    continue;
                }
                $this->expression("{$p}.when", $row['when'] ?? null);
                foreach ((array) ($row['outputs'] ?? []) as $k => $out) {
                    if ($k === 'outcome') {
                        $this->fail("{$p}.outputs.outcome", 'Rows cannot set the outcome; P1 automation may approve or refer but never auto-decline (D-038b).');
                    }
                    if (is_string($out) && str_starts_with($out, '=')) {
                        $this->expression("{$p}.outputs.{$k}", substr($out, 1));
                    }
                }
                if (in_array($table, ['knockouts', 'policy'], true) && ! is_string($row['reason']['code'] ?? null)) {
                    $this->fail("{$p}.reason.code", 'Knock-out and policy rows need a reason code (FR-CMP-043).');
                }
                if (isset($row['reason']['code']) && (! is_string($row['reason']['code']) || preg_match('/^[A-Z][A-Z0-9_]{1,47}$/', $row['reason']['code']) !== 1)) {
                    $this->fail("{$p}.reason.code", 'Reason codes are UPPER_SNAKE_CASE.');
                }
            }
        }
        if (isset($c['affordability'])) {
            $a = (array) $c['affordability'];
            $max = $a['max_dsr_percent'] ?? null;
            if (! is_string($max) || preg_match('/^\d{1,3}(\.\d{1,4})?$/', $max) !== 1 || BigDecimal::of($max)->isGreaterThan(100)) {
                $this->fail('affordability.max_dsr_percent', 'max_dsr_percent must be a decimal string between 0 and 100.');
            }
            if (! is_string($a['income_fact'] ?? null) || ! $this->knownRoot($a['income_fact'])) {
                $this->fail('affordability.income_fact', 'income_fact must name a fact, e.g. applicant.monthly_income.');
            }
            if (! is_string($a['dsr_formula'] ?? null) || ! in_array($a['dsr_formula'], $names, true)) {
                $this->fail('affordability.dsr_formula', 'dsr_formula must name one of the formulas.');
            }
        }
    }

    private function expression(string $field, mixed $expr): void
    {
        if (! is_string($expr) || trim($expr) === '') {
            $this->fail($field, 'An expression is required.');

            return;
        }
        try {
            EvaluatorRegistry::check('1.0.0', $expr);
            foreach (EvaluatorRegistry::references('1.0.0', $expr) as $ref) {
                if (! $this->knownRoot($ref)) {
                    $this->fail($field, "Unknown fact '{$ref}'. Facts start with: ".implode(', ', self::FACT_ROOTS).'.');
                }
            }
        } catch (RuleError $e) {
            $this->fail($field, $e->getMessage());
        }
    }

    private function knownRoot(string $path): bool
    {
        return in_array(explode('.', $path)[0], self::FACT_ROOTS, true);
    }

    private function fail(string $field, string $message): void
    {
        $this->errors['content.'.$field][] = $message;
    }
}

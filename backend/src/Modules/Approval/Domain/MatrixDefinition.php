<?php

declare(strict_types=1);

namespace Fundly\Modules\Approval\Domain;

use Brick\Math\BigDecimal;
use Fundly\Shared\Exceptions\ValidationFailed;
use Fundly\Shared\Rules\DecisionTable;
use Fundly\Shared\Rules\EvaluatorRegistry;
use Fundly\Shared\Rules\RuleError;

/**
 * An `approval.matrix` configuration version (TRD §6.3, FR-APV-001/003/007).
 *
 *   evaluator_version  rules-engine version the routing table runs under
 *   currency           currency of every amount in the matrix
 *   levels[]           ordered authority levels {code, name, roles[], mode}
 *   routing            decision table; each row outputs `levels` (codes)
 *   escalation         {on_exception, min_exception_severity, on_no_eligible_approver}
 *   step_up            {above_amount}: votes on larger exposures need a fresh step-up
 *   validity_days      optional; otherwise the product's approval_validity_days
 *
 * Only `single` mode exists in P1; parallel and quorum are P3 (FR-APV-003 ◐).
 */
final class MatrixDefinition
{
    public const FACT_ROOTS = ['application', 'product', 'decision', 'exceptions', 'exposure', 'collateral', 'customer'];

    public const MODES = ['single'];

    public const SEVERITIES = ['low' => 1, 'medium' => 2, 'high' => 3];

    /** @var array<string, list<string>> */
    private array $errors = [];

    /** @param array<string, mixed> $content */
    private function __construct(private readonly array $content) {}

    /** @param array<string, mixed> $content */
    public static function validate(array $content): void
    {
        $m = new self($content);
        $m->check();
        if ($m->errors !== []) {
            throw new ValidationFailed($m->errors);
        }
    }

    /** @param array<string, mixed> $content validated content */
    public static function of(array $content): self
    {
        return new self($content);
    }

    public function evaluatorVersion(): string
    {
        return (string) ($this->content['evaluator_version'] ?? EvaluatorRegistry::CURRENT);
    }

    public function currency(): string
    {
        return (string) $this->content['currency'];
    }

    /** @return list<array{code: string, name: string, roles: list<string>, mode: string}> in authority order */
    public function levels(): array
    {
        $out = [];
        foreach ((array) $this->content['levels'] as $l) {
            $l = (array) $l;
            $out[] = ['code' => (string) $l['code'], 'name' => (string) $l['name'], 'roles' => array_values(array_map('strval', (array) $l['roles'])), 'mode' => (string) ($l['mode'] ?? 'single')];
        }

        return $out;
    }

    /**
     * Run the routing table.
     *
     * @param  array<string, mixed>  $facts
     * @return array{row: ?int, levels: list<string>, trace: list<array{row: int, when: string, result: bool}>}
     */
    public function route(array $facts): array
    {
        $result = DecisionTable::evaluate((array) $this->content['routing'], EvaluatorRegistry::for($this->evaluatorVersion(), $facts));
        $hit = $result['matched'][0] ?? null;
        $codes = $hit === null ? [] : array_values(array_map('strval', (array) ($hit['outputs']['levels'] ?? [])));

        return ['row' => $hit['row'] ?? null, 'levels' => $codes, 'trace' => $result['trace']];
    }

    /** Escalate one level when an exception at or above this severity is present (FR-APV-007). */
    public function exceptionEscalates(int $count, ?string $maxSeverity): bool
    {
        $e = (array) ($this->content['escalation'] ?? []);
        if (($e['on_exception'] ?? true) !== true || $count === 0 || $maxSeverity === null) {
            return false;
        }
        $min = is_string($e['min_exception_severity'] ?? null) ? $e['min_exception_severity'] : 'low';

        return (self::SEVERITIES[$maxSeverity] ?? 0) >= (self::SEVERITIES[$min] ?? 1);
    }

    public function escalatesWhenUnstaffed(): bool
    {
        return (((array) ($this->content['escalation'] ?? []))['on_no_eligible_approver'] ?? true) === true;
    }

    /** Null means every vote needs a step-up. */
    public function stepUpAbove(): ?string
    {
        $s = ((array) ($this->content['step_up'] ?? []))['above_amount'] ?? null;

        return is_string($s) ? $s : null;
    }

    public function validityDays(): ?int
    {
        $v = $this->content['validity_days'] ?? null;

        return is_int($v) ? $v : null;
    }

    private function check(): void
    {
        $c = $this->content;
        $version = $c['evaluator_version'] ?? null;
        if (! is_string($version) || ! EvaluatorRegistry::supports($version)) {
            $this->fail('evaluator_version', 'evaluator_version must be one of: '.implode(', ', EvaluatorRegistry::versions()).'.');
        }
        if (! is_string($c['currency'] ?? null) || preg_match('/^[A-Z]{3}$/', $c['currency']) !== 1) {
            $this->fail('currency', 'currency must be an ISO 4217 code.');
        }
        $codes = [];
        $levels = $c['levels'] ?? null;
        if (! is_array($levels) || $levels === [] || ! array_is_list($levels)) {
            $this->fail('levels', 'levels must be a non-empty list, lowest authority first.');
            $levels = [];
        }
        foreach ($levels as $i => $l) {
            $code = is_array($l) ? ($l['code'] ?? null) : null;
            if (! is_string($code) || preg_match('/^[A-Z][A-Z0-9_]{0,15}$/', $code) !== 1 || in_array($code, $codes, true)) {
                $this->fail("levels.{$i}.code", 'Level codes are unique UPPER_SNAKE_CASE (e.g. L1).');
            } else {
                $codes[] = $code;
            }
            if (! is_string($l['name'] ?? null) || trim($l['name']) === '') {
                $this->fail("levels.{$i}.name", 'A level needs a name.');
            }
            $roles = is_array($l) ? ($l['roles'] ?? null) : null;
            if (! is_array($roles) || $roles === [] || array_filter($roles, static fn (mixed $r): bool => ! is_string($r) || preg_match('/^[a-z][a-z0-9_]{0,63}$/', $r) !== 1) !== []) {
                $this->fail("levels.{$i}.roles", 'roles must list the role codes eligible at this level.');
            }
            $mode = is_array($l) ? ($l['mode'] ?? 'single') : 'single';
            if (! in_array($mode, self::MODES, true)) {
                $this->fail("levels.{$i}.mode", 'Only single-approver levels are available; parallel and quorum committees arrive in P3 (FR-APV-003).');
            }
        }
        $routing = $c['routing'] ?? null;
        if (! is_array($routing) || ! is_array($routing['rows'] ?? null) || $routing['rows'] === []) {
            $this->fail('routing', 'routing must be a decision table with at least one row.');
        } else {
            if (isset($routing['hit_policy']) && ! in_array($routing['hit_policy'], ['FIRST', 'UNIQUE', 'PRIORITY'], true)) {
                $this->fail('routing.hit_policy', 'routing.hit_policy must be FIRST, UNIQUE or PRIORITY (one row decides the chain).');
            }
            foreach ($routing['rows'] as $i => $row) {
                $p = "routing.rows.{$i}";
                if (! is_array($row)) {
                    $this->fail($p, 'Each row must be an object.');

                    continue;
                }
                $this->expression("{$p}.when", $row['when'] ?? null);
                $out = $row['outputs']['levels'] ?? null;
                if (! is_array($out) || $out === [] || array_diff($out, $codes) !== []) {
                    $this->fail("{$p}.outputs.levels", 'outputs.levels must list one or more level codes defined in levels.');
                }
            }
        }
        $esc = $c['escalation'] ?? [];
        if (! is_array($esc)) {
            $this->fail('escalation', 'escalation must be an object.');
        } else {
            foreach (['on_exception', 'on_no_eligible_approver'] as $flag) {
                if (isset($esc[$flag]) && ! is_bool($esc[$flag])) {
                    $this->fail("escalation.{$flag}", "{$flag} must be true or false.");
                }
            }
            if (isset($esc['min_exception_severity']) && ! isset(self::SEVERITIES[$esc['min_exception_severity']])) {
                $this->fail('escalation.min_exception_severity', 'min_exception_severity must be low, medium or high.');
            }
        }
        $above = ((array) ($c['step_up'] ?? []))['above_amount'] ?? null;
        if ($above !== null && (! is_string($above) || preg_match('/^\d{1,16}(\.\d{1,4})?$/', $above) !== 1)) {
            $this->fail('step_up.above_amount', 'above_amount must be a non-negative decimal string.');
        }
        $days = $c['validity_days'] ?? null;
        if ($days !== null && (! is_int($days) || $days < 1 || $days > 365)) {
            $this->fail('validity_days', 'validity_days must be between 1 and 365.');
        }
    }

    private function expression(string $field, mixed $expr): void
    {
        if (! is_string($expr) || trim($expr) === '') {
            $this->fail($field, 'An expression is required.');

            return;
        }
        try {
            EvaluatorRegistry::check(EvaluatorRegistry::CURRENT, $expr);
            foreach (EvaluatorRegistry::references(EvaluatorRegistry::CURRENT, $expr) as $ref) {
                if (! in_array(explode('.', $ref)[0], self::FACT_ROOTS, true)) {
                    $this->fail($field, "Unknown fact '{$ref}'. Facts start with: ".implode(', ', self::FACT_ROOTS).'.');
                }
            }
        } catch (RuleError $e) {
            $this->fail($field, $e->getMessage());
        }
    }

    private function fail(string $field, string $message): void
    {
        $this->errors['content.'.$field][] = $message;
    }

    /** True when an amount is above the step-up threshold (or there is no threshold). */
    public function needsStepUp(BigDecimal $exposure): bool
    {
        $above = $this->stepUpAbove();

        return $above === null || $exposure->isGreaterThan(BigDecimal::of($above));
    }
}

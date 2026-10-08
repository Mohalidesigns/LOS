<?php

declare(strict_types=1);

namespace Fundly\Shared\Rules\Evaluators\V1;

use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Brick\Math\RoundingMode;
use Fundly\Shared\Rules\RuleError;

/**
 * Evaluator V1 (TRD §7.2). Pure and deterministic: no I/O, no clock, no
 * object access, only the whitelisted functions below. Every number is an
 * exact decimal (brick/math); division keeps 10 places, half-even.
 *
 * Missing facts are null. Arithmetic with null yields null; ordering
 * comparisons with null are false; `== null` tests for absence.
 *
 * Never change the semantics of a released evaluator: add V2 instead, so
 * stored decisions replay with the engine that made them (FR-CRD-014).
 */
final class Evaluator
{
    public const VERSION = '1.0.0';

    private const SCALE = 10;

    public const FUNCTIONS = ['min', 'max', 'abs', 'round', 'round_bankers', 'coalesce', 'between', 'pmt', 'percent', 'len', 'contains'];

    /** @param array<string, mixed> $facts nested arrays; numbers as int, numeric string or BigDecimal */
    public function __construct(private readonly array $facts) {}

    public function evaluate(string $expression): mixed
    {
        return $this->node(Parser::parse($expression));
    }

    /** Evaluate and require a boolean (conditions of decision-table rows). */
    public function test(string $expression): bool
    {
        $v = $this->evaluate($expression);
        if (! is_bool($v) && $v !== null) {
            throw new RuleError("Condition must be true or false: {$expression}");
        }

        return $v === true;
    }

    /**
     * Names referenced by an expression, for validation against a fact schema.
     *
     * @return list<string>
     */
    public static function references(string $expression): array
    {
        $out = [];
        $walk = static function (array $n) use (&$walk, &$out): void {
            if ($n[0] === 'var') {
                $out[] = (string) $n[1];
            }
            foreach ($n as $child) {
                if (is_array($child)) {
                    if (isset($child[0]) && is_string($child[0])) {
                        $walk($child);
                    } else {
                        foreach ($child as $c) {
                            if (is_array($c)) {
                                $walk($c);
                            }
                        }
                    }
                }
            }
        };
        $walk(Parser::parse($expression));

        return array_values(array_unique($out));
    }

    /** Validate syntax and function names without evaluating. */
    public static function check(string $expression): void
    {
        $walk = static function (array $n) use (&$walk): void {
            if ($n[0] === 'call' && ! in_array($n[1], self::FUNCTIONS, true)) {
                throw new RuleError("Unknown function {$n[1]}().");
            }
            foreach ($n as $child) {
                if (is_array($child)) {
                    if (isset($child[0]) && is_string($child[0])) {
                        $walk($child);
                    } else {
                        foreach ($child as $c) {
                            if (is_array($c)) {
                                $walk($c);
                            }
                        }
                    }
                }
            }
        };
        $walk(Parser::parse($expression));
    }

    /** @param array<int, mixed> $n */
    private function node(array $n): mixed
    {
        return match ($n[0]) {
            'num' => BigDecimal::of((string) $n[1]),
            'str' => (string) $n[1],
            'bool' => (bool) $n[1],
            'null' => null,
            'var' => $this->lookup((string) $n[1]),
            'list' => array_map(fn (array $c): mixed => $this->node($c), (array) $n[1]),
            'unary' => $this->unary((string) $n[1], $this->node((array) $n[2])),
            'binary' => $this->binary((string) $n[1], (array) $n[2], (array) $n[3]),
            'cond' => $this->truthy($this->node((array) $n[1])) ? $this->node((array) $n[2]) : $this->node((array) $n[3]),
            'call' => $this->call((string) $n[1], array_values(array_map(fn (array $c): mixed => $this->node($c), (array) $n[2]))),
            default => throw new RuleError('Unknown expression node.'),
        };
    }

    private function lookup(string $path): mixed
    {
        $v = $this->facts;
        foreach (explode('.', $path) as $key) {
            if (! is_array($v) || ! array_key_exists($key, $v)) {
                return null;
            }
            $v = $v[$key];
        }

        return self::normalise($v);
    }

    private static function normalise(mixed $v): mixed
    {
        if (is_int($v)) {
            return BigDecimal::of($v);
        }
        if (is_array($v) && array_is_list($v)) {
            return array_map(self::normalise(...), $v);
        }

        return $v;
    }

    private function unary(string $op, mixed $v): mixed
    {
        if ($op === 'not') {
            return ! $this->truthy($v);
        }
        $d = self::decimal($v);

        return $d?->negated();
    }

    /**
     * @param  array<int, mixed>  $l
     * @param  array<int, mixed>  $r
     */
    private function binary(string $op, array $l, array $r): mixed
    {
        if ($op === 'and') {
            return $this->truthy($this->node($l)) && $this->truthy($this->node($r));
        }
        if ($op === 'or') {
            return $this->truthy($this->node($l)) || $this->truthy($this->node($r));
        }
        $a = $this->node($l);
        $b = $this->node($r);
        switch ($op) {
            case 'in':
            case 'not in':
                if (! is_array($b)) {
                    throw new RuleError("Right side of '{$op}' must be a list.");
                }
                $found = false;
                foreach ($b as $item) {
                    if (self::equals($a, $item)) {
                        $found = true;
                        break;
                    }
                }

                return $op === 'in' ? $found : ! $found;
            case '==':
                return self::equals($a, $b);
            case '!=':
                return ! self::equals($a, $b);
            case '<': case '<=': case '>': case '>=':
                $x = self::decimal($a);
                $y = self::decimal($b);
                if ($x === null || $y === null) {
                    return false;
                }
                $c = $x->compareTo($y);

                return match ($op) {
                    '<' => $c < 0, '<=' => $c <= 0, '>' => $c > 0, default => $c >= 0,
                };
        }
        $x = self::decimal($a);
        $y = self::decimal($b);
        if ($x === null || $y === null) {
            return null;
        }
        try {
            return match ($op) {
                '+' => $x->plus($y),
                '-' => $x->minus($y),
                '*' => $x->multipliedBy($y),
                '/' => $y->isZero() ? null : $x->dividedBy($y, self::SCALE, RoundingMode::HalfEven),
                '%' => $y->isZero() ? null : $x->remainder($y),
                default => throw new RuleError("Unknown operator {$op}."),
            };
        } catch (MathException $e) {
            throw new RuleError('Arithmetic error: '.$e->getMessage());
        }
    }

    /** @param list<mixed> $a */
    private function call(string $fn, array $a): mixed
    {
        $num = static fn (int $i): ?BigDecimal => self::decimal($a[$i] ?? null);

        return match ($fn) {
            'min', 'max' => $this->extreme($fn, $a),
            'abs' => $num(0)?->abs(),
            'round', 'round_bankers' => $num(0)?->toScale(max(0, (int) (string) ($num(1) ?? BigDecimal::zero())), RoundingMode::HalfEven),
            'coalesce' => array_values(array_filter($a, static fn ($v): bool => $v !== null))[0] ?? null,
            'between' => ($x = $num(0)) !== null && ($lo = $num(1)) !== null && ($hi = $num(2)) !== null && ! $x->isLessThan($lo) && ! $x->isGreaterThan($hi),
            'percent' => ($p = $num(0)) !== null && ($w = $num(1)) !== null && ! $w->isZero() ? $p->multipliedBy(100)->dividedBy($w, self::SCALE, RoundingMode::HalfEven) : null,
            'pmt' => self::pmt($num(0), $num(1), $num(2)),
            'len' => is_array($a[0] ?? null) ? BigDecimal::of(count($a[0])) : (is_string($a[0] ?? null) ? BigDecimal::of(mb_strlen($a[0])) : null),
            'contains' => is_string($a[0] ?? null) && is_string($a[1] ?? null) && str_contains(mb_strtolower($a[0]), mb_strtolower($a[1])),
            default => throw new RuleError("Unknown function {$fn}()."),
        };
    }

    /** @param list<mixed> $a */
    private function extreme(string $fn, array $a): ?BigDecimal
    {
        $vals = array_values(array_filter(array_map(self::decimal(...), count($a) === 1 && is_array($a[0]) ? $a[0] : $a), static fn ($v): bool => $v !== null));
        if ($vals === []) {
            return null;
        }

        return $fn === 'min' ? BigDecimal::min(...$vals) : BigDecimal::max(...$vals);
    }

    /** Level payment for a periodic rate r over n periods on principal pv (annuity), r = 0 → pv / n. */
    private static function pmt(?BigDecimal $r, ?BigDecimal $n, ?BigDecimal $pv): ?BigDecimal
    {
        if ($r === null || $n === null || $pv === null || ! $n->isPositive()) {
            return null;
        }
        $periods = max(1, (int) (string) $n->toScale(0, RoundingMode::Down));
        if ($r->isZero()) {
            return $pv->dividedBy($periods, self::SCALE, RoundingMode::HalfEven);
        }
        $growth = BigDecimal::one()->plus($r)->power($periods);
        $factor = $r->multipliedBy($growth)->dividedBy($growth->minus(1), self::SCALE * 2, RoundingMode::HalfEven);

        return $pv->multipliedBy($factor)->toScale(self::SCALE, RoundingMode::HalfEven);
    }

    private function truthy(mixed $v): bool
    {
        if (is_bool($v)) {
            return $v;
        }
        if ($v === null) {
            return false;
        }
        throw new RuleError('Expected a true/false value.');
    }

    private static function decimal(mixed $v): ?BigDecimal
    {
        if ($v instanceof BigDecimal) {
            return $v;
        }
        if (is_int($v)) {
            return BigDecimal::of($v);
        }
        if (is_string($v) && preg_match('/^-?\d+(\.\d+)?$/', $v) === 1) {
            return BigDecimal::of($v);
        }

        return null;
    }

    private static function equals(mixed $a, mixed $b): bool
    {
        $x = self::decimal($a);
        $y = self::decimal($b);
        if ($x !== null && $y !== null && ($a instanceof BigDecimal || $b instanceof BigDecimal)) {
            return $x->isEqualTo($y);
        }

        return $a === $b;
    }
}

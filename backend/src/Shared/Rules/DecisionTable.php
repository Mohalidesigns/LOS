<?php

declare(strict_types=1);

namespace Fundly\Shared\Rules;

use Brick\Math\BigDecimal;
use Fundly\Shared\Rules\Evaluators\V1\Evaluator;

/**
 * Decision table (TRD §7.1): rows of `when` conditions with outputs and an
 * optional reason; hit policies UNIQUE, FIRST, PRIORITY, COLLECT. Outputs
 * that are strings starting with "=" are expressions; anything else is a
 * literal. Every row's result goes into the trace.
 */
final class DecisionTable
{
    public const HIT_POLICIES = ['UNIQUE', 'FIRST', 'PRIORITY', 'COLLECT'];

    /**
     * @param  array{name?: string, hit_policy?: string, rows?: list<array<string, mixed>>}  $table
     * @return array{matched: list<array{row: int, outputs: array<string, mixed>, reason: ?array{code: string, text_key: string}}>, trace: list<array{row: int, when: string, result: bool}>}
     */
    public static function evaluate(array $table, Evaluator $eval): array
    {
        $policy = $table['hit_policy'] ?? 'FIRST';
        $rows = $table['rows'] ?? [];
        $matched = [];
        $trace = [];
        foreach ($rows as $i => $row) {
            $when = is_string($row['when'] ?? null) ? $row['when'] : 'true';
            $hit = $eval->test($when);
            $trace[] = ['row' => $i, 'when' => $when, 'result' => $hit];
            if (! $hit) {
                continue;
            }
            $outputs = [];
            foreach ((array) ($row['outputs'] ?? []) as $k => $v) {
                $value = is_string($v) && str_starts_with($v, '=') ? $eval->evaluate(substr($v, 1)) : $v;
                $outputs[(string) $k] = $value instanceof BigDecimal ? (string) $value : $value;
            }
            $reason = is_array($row['reason'] ?? null) && is_string($row['reason']['code'] ?? null)
                ? ['code' => $row['reason']['code'], 'text_key' => is_string($row['reason']['text_key'] ?? null) ? $row['reason']['text_key'] : 'reason.'.strtolower($row['reason']['code'])]
                : null;
            $matched[] = ['row' => $i, 'priority' => is_int($row['priority'] ?? null) ? $row['priority'] : 0, 'outputs' => $outputs, 'reason' => $reason];
            if ($policy === 'FIRST') {
                break;
            }
        }
        if ($policy === 'UNIQUE' && count($matched) > 1) {
            throw new RuleError(sprintf("Decision table '%s' is UNIQUE but %d rows matched.", $table['name'] ?? 'table', count($matched)));
        }
        if ($policy === 'PRIORITY' && $matched !== []) {
            usort($matched, static fn (array $a, array $b): int => $b['priority'] <=> $a['priority'] ?: $a['row'] <=> $b['row']);
            $matched = [$matched[0]];
        }

        return ['matched' => array_values(array_map(static fn (array $m): array => ['row' => $m['row'], 'outputs' => $m['outputs'], 'reason' => $m['reason']], $matched)), 'trace' => $trace];
    }
}

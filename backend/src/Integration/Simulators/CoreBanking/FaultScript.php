<?php

declare(strict_types=1);

namespace Fundly\Integration\Simulators\CoreBanking;

use InvalidArgumentException;

/**
 * Failure injection for the simulator (integration register §3):
 *
 *   latency(ms) · timeout · timeout_then_success · duplicate · partial(step) ·
 *   reject(code) · error_rate(percent)
 *
 * A script is a list of rules: {operation: "<op>"|"*", fault: "...", ...params,
 * times?: n}. `times` limits how many calls the rule affects (default: all).
 * Ignored entirely in a production installation.
 */
final readonly class FaultScript
{
    public const FAULTS = ['latency', 'timeout', 'timeout_then_success', 'duplicate', 'partial', 'reject', 'error_rate'];

    /** @param list<array{operation: string, fault: string, ms?: int, code?: string, step?: int, percent?: int, seed?: int, times?: int}> $rules */
    public function __construct(public array $rules)
    {
        foreach ($rules as $i => $r) {
            if (! in_array($r['fault'], self::FAULTS, true)) {
                throw new InvalidArgumentException("Fault rule {$i}: unknown fault {$r['fault']}.");
            }
            if ($r['fault'] === 'reject' && ! isset($r['code'])) {
                throw new InvalidArgumentException("Fault rule {$i}: reject needs a code.");
            }
            if ($r['fault'] === 'error_rate' && (! isset($r['percent']) || $r['percent'] < 0 || $r['percent'] > 100)) {
                throw new InvalidArgumentException("Fault rule {$i}: error_rate needs percent 0-100.");
            }
            if ($r['fault'] === 'partial' && (! isset($r['step']) || $r['step'] < 1)) {
                throw new InvalidArgumentException("Fault rule {$i}: partial needs step >= 1.");
            }
        }
    }

    /** @param array<array-key, mixed>|null $data */
    public static function fromArray(?array $data): self
    {
        if ($data === null) {
            return new self([]);
        }
        $rules = $data['rules'] ?? $data;
        if (! is_array($rules) || ! array_is_list($rules)) {
            throw new InvalidArgumentException('Fault script must be a list of rules.');
        }
        $out = [];
        foreach ($rules as $r) {
            if (! is_array($r) || ! isset($r['fault']) || ! is_string($r['fault'])) {
                throw new InvalidArgumentException('Each fault rule needs a fault name.');
            }
            /** @var array{operation: string, fault: string, ms?: int, code?: string, step?: int, percent?: int, seed?: int, times?: int} $rule */
            $rule = ['operation' => is_string($r['operation'] ?? null) ? $r['operation'] : '*', 'fault' => $r['fault']];
            foreach (['ms', 'step', 'percent', 'seed', 'times'] as $k) {
                if (isset($r[$k]) && is_int($r[$k])) {
                    $rule[$k] = $r[$k];
                }
            }
            if (isset($r['code']) && is_string($r['code'])) {
                $rule['code'] = $r['code'];
            }
            $out[] = $rule;
        }

        return new self($out);
    }

    /** @return list<array{index: int, rule: array{operation: string, fault: string, ms?: int, code?: string, step?: int, percent?: int, seed?: int, times?: int}}> */
    public function rulesFor(string $operation): array
    {
        $out = [];
        foreach ($this->rules as $i => $r) {
            if ($r['operation'] === '*' || $r['operation'] === $operation) {
                $out[] = ['index' => $i, 'rule' => $r];
            }
        }

        return $out;
    }
}

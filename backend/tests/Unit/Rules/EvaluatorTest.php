<?php

declare(strict_types=1);

use Brick\Math\RoundingMode;
use Fundly\Shared\Rules\DecisionTable;
use Fundly\Shared\Rules\Evaluators\V1\Evaluator;
use Fundly\Shared\Rules\RuleError;

function ev(array $facts = []): Evaluator
{
    return new Evaluator($facts);
}

it('computes in exact decimals (no float error) with half-even division', function () {
    expect((string) ev()->evaluate('0.1 + 0.2'))->toBe('0.3')
        ->and((string) ev()->evaluate('1 / 3'))->toBe('0.3333333333')
        ->and((string) ev(['a' => '1000000.0005'])->evaluate('a * 3'))->toBe('3000000.0015')
        ->and((string) ev()->evaluate('round(2.345, 2)'))->toBe('2.34')
        ->and((string) ev()->evaluate('round_bankers(2.355, 2)'))->toBe('2.36');
})->group('FR-CRD-001');

it('reads nested facts, treats missing facts as null and supports logic, lists and ternaries', function () {
    $e = ev(['applicant' => ['age' => 34, 'type' => 'individual', 'sector' => 'agro'], 'bureau' => ['max_dpd' => '0']]);
    expect($e->test('applicant.age >= 18 and applicant.age <= 65'))->toBeTrue()
        ->and($e->test("applicant.type in ['individual', 'sole_proprietor']"))->toBeTrue()
        ->and($e->test("applicant.sector not in ['oil', 'gas']"))->toBeTrue()
        ->and($e->test('bureau.max_dpd == 0'))->toBeTrue()
        ->and($e->test('bureau.unknown > 5'))->toBeFalse()
        ->and($e->evaluate('bureau.unknown + 1'))->toBeNull()
        ->and($e->test('bureau.unknown == null'))->toBeTrue()
        ->and((string) $e->evaluate('coalesce(bureau.unknown, 7)'))->toBe('7')
        ->and($e->evaluate("applicant.age > 60 ? 'senior' : 'standard'"))->toBe('standard')
        ->and($e->test('!(applicant.age < 18) && between(applicant.age, 18, 70)'))->toBeTrue();
})->group('FR-CRD-001');

it('computes an annuity instalment and percentages for affordability', function () {
    // ₦10,000,000 over 12 months at 24% p.a. (2% per month) ≈ ₦945,595.97
    $e = ev(['facility' => ['amount' => '10000000', 'rate_percent' => '24', 'tenor_months' => 12], 'income' => '2500000']);
    $pmt = $e->evaluate('pmt(facility.rate_percent / 1200, facility.tenor_months, facility.amount)');
    expect((string) $pmt->toScale(2, RoundingMode::HalfEven))->toBe('945595.97')
        ->and((string) ev(['x' => '945595.96', 'inc' => '2500000'])->evaluate('round(percent(x, inc), 2)'))->toBe('37.82')
        ->and(ev()->evaluate('percent(1, 0)'))->toBeNull();
})->group('FR-CRD-008');

it('is sandboxed: only whitelisted functions, no method calls, syntax errors are explicit', function () {
    expect(fn () => Evaluator::check('system("ls")'))->toThrow(RuleError::class, 'Unknown function system()')
        ->and(fn () => ev()->evaluate('exec("id")'))->toThrow(RuleError::class)
        ->and(fn () => ev()->evaluate('1 +'))->toThrow(RuleError::class)
        ->and(fn () => ev()->evaluate('a.b(1)'))->toThrow(RuleError::class)
        ->and(fn () => ev()->evaluate('`x`'))->toThrow(RuleError::class)
        ->and(fn () => ev()->test('1 + 1'))->toThrow(RuleError::class, 'Condition must be true or false')
        ->and(Evaluator::references('applicant.age > 18 and bureau.score >= min(600, product.floor)'))->toBe(['applicant.age', 'bureau.score', 'product.floor']);
})->group('FR-CRD-001', 'FR-SEC-019');

it('evaluates decision tables under FIRST, COLLECT, PRIORITY and UNIQUE hit policies with a full trace', function () {
    $e = ev(['score' => 640, 'dsr' => '45']);
    $rows = [
        ['when' => 'score >= 700', 'outputs' => ['grade' => 'A'], 'priority' => 1],
        ['when' => 'score >= 600', 'outputs' => ['grade' => 'B', 'rate' => '=24 + 1.5'], 'reason' => ['code' => 'GRADE_B'], 'priority' => 2],
        ['when' => 'dsr > 40', 'outputs' => ['grade' => 'C'], 'reason' => ['code' => 'DSR_HIGH'], 'priority' => 3],
    ];
    $first = DecisionTable::evaluate(['hit_policy' => 'FIRST', 'rows' => $rows], $e);
    expect($first['matched'])->toHaveCount(1)->and($first['matched'][0]['outputs'])->toBe(['grade' => 'B', 'rate' => '25.5'])
        ->and($first['matched'][0]['reason'])->toBe(['code' => 'GRADE_B', 'text_key' => 'reason.grade_b'])
        ->and(array_column($first['trace'], 'result'))->toBe([false, true]);
    expect(array_column(array_column(DecisionTable::evaluate(['hit_policy' => 'COLLECT', 'rows' => $rows], $e)['matched'], 'outputs'), 'grade'))->toBe(['B', 'C'])
        ->and(DecisionTable::evaluate(['hit_policy' => 'PRIORITY', 'rows' => $rows], $e)['matched'][0]['outputs']['grade'])->toBe('C')
        ->and(fn () => DecisionTable::evaluate(['name' => 'grades', 'hit_policy' => 'UNIQUE', 'rows' => $rows], $e))->toThrow(RuleError::class, "Decision table 'grades' is UNIQUE but 2 rows matched.");
})->group('FR-CRD-002');

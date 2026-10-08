<?php

declare(strict_types=1);

use Brick\Math\RoundingMode;
use Fundly\Shared\Money\Currency;
use Fundly\Shared\Money\CurrencyMismatch;
use Fundly\Shared\Money\Money;

it('adds and subtracts exactly without float error', function () {
    $a = Money::of('0.1', 'NGN');
    $b = Money::of('0.2', 'NGN');
    expect($a->plus($b)->toStorage())->toBe('0.3000')
        ->and(Money::of('1000000000000.0001', 'NGN')->minus(Money::of('0.0001', 'NGN'))->toStorage())->toBe('1000000000000.0000');
});

it('rejects mixing currencies', function () {
    Money::of('1', 'NGN')->plus(Money::of('1', 'USD'));
})->throws(CurrencyMismatch::class);

it('rejects more precision than storage scale instead of silently rounding', function () {
    Money::of('1.00001', 'NGN');
})->throws(InvalidArgumentException::class);

it('rejects malformed amounts and invalid ISO codes', function (string $amount, string $ccy) {
    Money::of($amount, $ccy);
})->with([['1e3', 'NGN'], ['abc', 'NGN'], ['1', 'NG'], ['1', 'N1N']])->throws(InvalidArgumentException::class);

it('multiplies by a decimal rate with banker\'s rounding at storage scale', function () {
    // 1,234.5678 × 0.15 = 185.18517 → 185.1852 (HALF_EVEN)
    expect(Money::of('1234.5678', 'NGN')->multipliedBy('0.15')->toStorage())->toBe('185.1852')
        ->and(Money::of('0.5', 'NGN')->multipliedBy('0.00025')->toStorage())->toBe('0.0001')
        ->and(Money::of('1.5', 'NGN')->multipliedBy('0.00025')->toStorage())->toBe('0.0004');
});

it('rounds to currency minor units', function () {
    expect(Money::of('10.005', 'NGN')->roundedToMinor()->toStorage())->toBe('10.0000')
        ->and(Money::of('10.015', 'NGN')->roundedToMinor()->toStorage())->toBe('10.0200')
        ->and(Money::of('10.015', 'NGN')->roundedToMinor(RoundingMode::Down)->toStorage())->toBe('10.0100')
        ->and(Currency::of('xof')->minorUnits())->toBe(0);
});

it('round-trips through its array form and compares', function () {
    $m = Money::fromArray(['amount' => '5000000.50', 'currency' => 'NGN']);
    expect($m->toArray())->toBe(['amount' => '5000000.5000', 'currency' => 'NGN'])
        ->and($m->isGreaterThan(Money::of('5000000', 'NGN')))->toBeTrue()
        ->and($m->equals(Money::ofMinor(500000050, 'NGN')))->toBeTrue()
        ->and(json_encode($m))->toBe('{"amount":"5000000.5000","currency":"NGN"}');
});

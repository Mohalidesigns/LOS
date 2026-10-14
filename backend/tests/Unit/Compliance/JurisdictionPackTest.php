<?php

declare(strict_types=1);

use Brick\Math\BigDecimal;
use Fundly\Modules\Compliance\Domain\Packs\DefaultPacks;
use Fundly\Modules\Compliance\Domain\Packs\JurisdictionPack;
use Fundly\Modules\Compliance\Domain\PrudentialParameters;
use Fundly\Modules\Compliance\Domain\SingleObligor;
use Fundly\Shared\Exceptions\ValidationFailed;
use Fundly\Shared\Money\Money;

it('ships a valid, fully unverified Nigeria draft that cannot be activated', function () {
    $pack = DefaultPacks::nigeriaCommercialBankV0();
    JurisdictionPack::validate($pack);
    expect(fn () => JurisdictionPack::assertActivatable($pack, false))->toThrow(ValidationFailed::class);
    JurisdictionPack::assertActivatable($pack, true);
    expect(JurisdictionPack::key('NG', 'commercial_bank'))->toBe('ng.commercial_bank');
});

it('answers with the rule in force on a date, so past decisions can be re-evaluated (FR-CMP-003)', function () {
    $pack = DefaultPacks::nigeriaCommercialBankV0();
    $old = $pack['rules'][0];
    $old['effective_to'] = '2026-07-01';
    $new = $pack['rules'][0];
    $new['effective_from'] = '2026-07-01';
    $new['parameters']['limit_percent'] = '15';
    $pack['rules'][0] = $old;
    $pack['rules'][] = $new;
    JurisdictionPack::validate($pack);
    $p = JurisdictionPack::of($pack);
    expect($p->ruleAt('single_obligor_limit', new DateTimeImmutable('2026-06-30'))['parameters']['limit_percent'])->toBe('20')
        ->and($p->ruleAt('single_obligor_limit', new DateTimeImmutable('2026-07-01'))['parameters']['limit_percent'])->toBe('15')
        ->and($p->ruleAt('single_obligor_limit', new DateTimeImmutable('2019-01-01')))->toBeNull();
});

it('picks the prudential base in force and computes the limit in decimals', function () {
    $params = PrudentialParameters::of(['entries' => [
        ['effective_from' => '2026-01-01', 'shareholders_funds_unimpaired' => ['amount' => '100.00', 'currency' => 'NGN'], 'source' => 'a'],
        ['effective_from' => '2026-07-01', 'shareholders_funds_unimpaired' => ['amount' => '200.00', 'currency' => 'NGN'], 'source' => 'b'],
    ]]);
    expect($params->at(new DateTimeImmutable('2026-03-01'))['source'])->toBe('a')->and($params->at(new DateTimeImmutable('2026-08-01'))['source'])->toBe('b')
        ->and($params->at(new DateTimeImmutable('2025-12-31')))->toBeNull();
    $calc = new SingleObligor(Money::of('250000000000.00', 'NGN'), BigDecimal::of('20'), Money::of('49000000000.00', 'NGN'), Money::of('1500000000.00', 'NGN'));
    expect($calc->limit->toStorage())->toBe('50000000000.0000')->and($calc->breached())->toBeTrue()->and($calc->utilisationPercent())->toBe('20.20')
        ->and($calc->headroom()->toStorage())->toBe('-500000000.0000');
    expect(fn () => PrudentialParameters::validate(['entries' => [['effective_from' => '2026-01-01', 'shareholders_funds_unimpaired' => ['amount' => 'lots'], 'source' => '']]]))->toThrow(ValidationFailed::class);
});

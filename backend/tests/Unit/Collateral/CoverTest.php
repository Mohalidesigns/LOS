<?php

declare(strict_types=1);

use Brick\Math\BigDecimal;
use Fundly\Modules\Collateral\Domain\CollateralPolicy;
use Fundly\Modules\Collateral\Domain\CollateralType;
use Fundly\Modules\Collateral\Domain\Cover;
use Fundly\Modules\Collateral\Domain\DefaultCollateralTypes;
use Fundly\Shared\Exceptions\ValidationFailed;
use Fundly\Shared\Money\Money;

it('computes forced-sale value, cover and LTV in decimals', function () {
    $market = Money::of('6000000.00', 'NGN');
    $fsv = Cover::forcedSaleValue($market, BigDecimal::of('30'));
    expect($fsv->toStorage())->toBe('4200000.0000');
    $cover = new Cover(Money::of('3000000.00', 'NGN'), $fsv, $market);
    expect($cover->coverPercent())->toBe('140.00')->and($cover->ltvPercent())->toBe('50.00')
        ->and($cover->meetsCover(BigDecimal::of('120')))->toBeTrue()->and($cover->meetsLtv(BigDecimal::of('40')))->toBeFalse()
        ->and($cover->meetsCover(null))->toBeNull();
    // one third of a 1,000,000.00 item, haircut 0
    expect(Cover::forcedSaleValue(Money::of('1000000.00', 'NGN'), BigDecimal::of('0'))->toStorage())->toBe('1000000.0000')
        ->and(Cover::marketShare(Money::of('1500000.00', 'NGN'), Money::of('900000.00', 'NGN'), Money::of('300000.00', 'NGN'))->toStorage())->toBe('500000.0000')
        ->and(Cover::effective(Money::of('700000.00', 'NGN'), Money::of('1200000.00', 'NGN'), Money::of('900000.00', 'NGN'))->toStorage())->toBe('525000.0000')
        ->and(Cover::effective(Money::of('700000.00', 'NGN'), Money::of('700000.00', 'NGN'), Money::of('900000.00', 'NGN'))->toStorage())->toBe('700000.0000');
    expect((new Cover(Money::zero('NGN'), Money::zero('NGN'), Money::zero('NGN')))->coverPercent())->toBeNull();
});

it('ships valid defaults for every BRD type and validates tenant types', function () {
    foreach (DefaultCollateralTypes::all() as $content) {
        CollateralType::validate($content);
    }
    expect(array_unique(array_map(static fn (array $c): string => $c['category'], DefaultCollateralTypes::all())))->toHaveCount(count(CollateralType::CATEGORIES));
    $type = CollateralType::of(DefaultCollateralTypes::all()['real_estate']);
    expect(array_column($type->perfectionSteps('FC'), 'name'))->toContain("Minister's consent (FCT, AGIS)")
        ->and(array_column($type->perfectionSteps('LA'), 'name'))->toContain("Governor's consent to the mortgage");
    expect(fn () => CollateralType::validate(['name' => 'X', 'category' => 'vehicle', 'haircut_percent' => '10', 'requires_valuation' => true, 'requires_insurance' => true,
        'perfection' => ['default' => [['code' => 'a_step', 'name' => 'A', 'timing' => 'precedent'], ['code' => 'a_step', 'name' => 'B', 'timing' => 'precedent']], 'by_state' => ['lagos' => []]]]))
        ->toThrow(ValidationFailed::class);
});

it('lets a product move perfection steps between precedent and subsequent', function () {
    $policy = CollateralPolicy::fromProduct(['collateral' => ['required' => true, 'min_cover_percent' => '120', 'perfection_timing' => ['consent' => 'precedent', 'x' => 'never']]]);
    expect($policy->timing('consent', 'subsequent'))->toBe('precedent')->and($policy->timing('x', 'subsequent'))->toBe('subsequent')
        ->and($policy->toArray())->toBe(['required' => true, 'min_cover_percent' => '120', 'max_ltv_percent' => null]);
    expect(CollateralPolicy::fromProduct([])->required)->toBeFalse();
});

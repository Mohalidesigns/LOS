<?php

declare(strict_types=1);

namespace Fundly\Modules\Collateral\Domain;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Fundly\Shared\Money\Money;

/**
 * Security cover arithmetic (FR-COL-002), in decimals only.
 *
 * Allocations are made in lending value (forced-sale value after haircut).
 * The market value that goes with an allocation is the same share of the
 * item's market value. Cover = lending value ÷ exposure; LTV = exposure ÷
 * market value; both as percentages to 2 places.
 */
final readonly class Cover
{
    public function __construct(public Money $exposure, public Money $lendingValue, public Money $marketValue) {}

    public static function forcedSaleValue(Money $market, BigDecimal $haircutPercent): Money
    {
        $factor = BigDecimal::of(100)->minus($haircutPercent)->dividedBy(100, 6, RoundingMode::HalfEven);

        return $market->multipliedBy($factor)->roundedToMinor();
    }

    /** The market value that goes with an allocation of lending value. */
    public static function marketShare(Money $market, Money $forcedSale, Money $allocated): Money
    {
        if ($forcedSale->isZero()) {
            return Money::zero($market->currency);
        }
        $share = $allocated->amount->dividedBy($forcedSale->amount, 10, RoundingMode::HalfEven);

        return $market->multipliedBy($share)->roundedToMinor();
    }

    /**
     * Allocations scaled down pro rata when they exceed the item's lending value
     * (after a downward re-valuation); otherwise unchanged.
     */
    public static function effective(Money $allocated, Money $allocatedTotal, Money $forcedSale): Money
    {
        if (! $allocatedTotal->isGreaterThan($forcedSale) || $allocatedTotal->isZero()) {
            return $allocated;
        }

        return $allocated->multipliedBy($forcedSale->amount->dividedBy($allocatedTotal->amount, 10, RoundingMode::HalfEven))->roundedToMinor();
    }

    public function coverPercent(): ?string
    {
        return $this->exposure->isZero() ? null : self::percent($this->lendingValue->amount, $this->exposure->amount);
    }

    public function ltvPercent(): ?string
    {
        return $this->marketValue->isZero() ? null : self::percent($this->exposure->amount, $this->marketValue->amount);
    }

    public function meetsCover(?BigDecimal $min): ?bool
    {
        $cover = $this->coverPercent();

        return $min === null ? null : ($cover !== null && ! BigDecimal::of($cover)->isLessThan($min));
    }

    public function meetsLtv(?BigDecimal $max): ?bool
    {
        $ltv = $this->ltvPercent();

        return $max === null ? null : ($ltv !== null && ! BigDecimal::of($ltv)->isGreaterThan($max));
    }

    private static function percent(BigDecimal $a, BigDecimal $b): string
    {
        return (string) $a->multipliedBy(100)->dividedBy($b, 2, RoundingMode::HalfEven);
    }
}

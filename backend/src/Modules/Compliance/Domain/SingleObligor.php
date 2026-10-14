<?php

declare(strict_types=1);

namespace Fundly\Modules\Compliance\Domain;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Fundly\Shared\Money\Money;

/**
 * Single-obligor arithmetic (FR-CMP-023): the obligor's existing exposure
 * at the bank plus this facility against limit_percent × base. Decimal only.
 */
final readonly class SingleObligor
{
    public Money $limit;

    public Money $total;

    public function __construct(public Money $base, public BigDecimal $limitPercent, public Money $existing, public Money $proposed)
    {
        $this->limit = $base->multipliedBy($limitPercent->dividedBy(100, 6, RoundingMode::HalfEven))->roundedToMinor();
        $this->total = $existing->plus($proposed);
    }

    public function breached(): bool
    {
        return $this->total->isGreaterThan($this->limit);
    }

    public function headroom(): Money
    {
        return $this->limit->minus($this->total);
    }

    /** Total exposure as a percentage of the base, 2 places. */
    public function utilisationPercent(): string
    {
        return $this->base->isZero() ? '0.00' : (string) $this->total->amount->multipliedBy(100)->dividedBy($this->base->amount, 2, RoundingMode::HalfEven);
    }
}

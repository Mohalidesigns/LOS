<?php

declare(strict_types=1);

namespace Fundly\Shared\Money;

use Brick\Math\BigDecimal;
use Brick\Math\BigNumber;
use Brick\Math\RoundingMode;
use InvalidArgumentException;
use JsonSerializable;

/**
 * Immutable monetary amount. Arbitrary precision via brick/math; never floats
 * (TRD §4.1). Stored as numeric(20,4) + ISO 4217 code, so the internal scale is 4.
 */
final readonly class Money implements JsonSerializable
{
    public const STORAGE_SCALE = 4;

    private function __construct(public BigDecimal $amount, public Currency $currency)
    {
    }

    /**
     * @param  BigNumber|int|string  $amount  decimal string or integer; floats are rejected by type
     */
    public static function of(BigNumber|int|string $amount, Currency|string $currency): self
    {
        if (is_string($amount) && preg_match('/^-?\d+(\.\d+)?$/', trim($amount)) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid money amount "%s".', $amount));
        }

        $currency = $currency instanceof Currency ? $currency : Currency::of($currency);
        $decimal = BigDecimal::of($amount);
        if ($decimal->getScale() > self::STORAGE_SCALE) {
            throw new InvalidArgumentException(sprintf(
                'Money amount "%s" has more than %d decimal places; round explicitly first.',
                (string) $decimal,
                self::STORAGE_SCALE,
            ));
        }

        return new self($decimal->toScale(self::STORAGE_SCALE), $currency);
    }

    public static function zero(Currency|string $currency): self
    {
        return self::of(0, $currency);
    }

    /** Amount in minor units (e.g. kobo), rounding HALF_EVEN if needed. */
    public static function ofMinor(int $minor, Currency|string $currency): self
    {
        $currency = $currency instanceof Currency ? $currency : Currency::of($currency);

        return self::of(BigDecimal::ofUnscaledValue($minor, $currency->minorUnits()), $currency);
    }

    public function plus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount->plus($other->amount), $this->currency);
    }

    public function minus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount->minus($other->amount), $this->currency);
    }

    /** Multiply by a decimal factor (e.g. a rate), rounding to storage scale. */
    public function multipliedBy(BigNumber|int|string $factor, RoundingMode $rounding = RoundingMode::HalfEven): self
    {
        return new self(
            $this->amount->multipliedBy($factor)->toScale(self::STORAGE_SCALE, $rounding),
            $this->currency,
        );
    }

    /** Round to the currency's minor units (e.g. for posting to a CBA). */
    public function roundedToMinor(RoundingMode $rounding = RoundingMode::HalfEven): self
    {
        return new self(
            $this->amount->toScale($this->currency->minorUnits(), $rounding)->toScale(self::STORAGE_SCALE),
            $this->currency,
        );
    }

    public function compareTo(self $other): int
    {
        $this->assertSameCurrency($other);

        return $this->amount->compareTo($other->amount);
    }

    public function isGreaterThan(self $other): bool
    {
        return $this->compareTo($other) > 0;
    }

    public function isLessThanOrEqualTo(self $other): bool
    {
        return $this->compareTo($other) <= 0;
    }

    public function isZero(): bool
    {
        return $this->amount->isZero();
    }

    public function isNegative(): bool
    {
        return $this->amount->isNegative();
    }

    public function equals(self $other): bool
    {
        return $this->currency->equals($other->currency) && $this->amount->isEqualTo($other->amount);
    }

    /** Decimal string at storage scale, e.g. "1500000.0000". */
    public function toStorage(): string
    {
        return (string) $this->amount;
    }

    /** @return array{amount: string, currency: string} */
    public function toArray(): array
    {
        return ['amount' => $this->toStorage(), 'currency' => $this->currency->code];
    }

    /** @return array{amount: string, currency: string} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /** @param  array{amount?: mixed, currency?: mixed}  $data */
    public static function fromArray(array $data): self
    {
        $amount = $data['amount'] ?? null;
        $currency = $data['currency'] ?? null;
        if (! is_string($amount) && ! is_int($amount)) {
            throw new InvalidArgumentException('Money amount must be a decimal string or integer.');
        }
        if (! is_string($currency)) {
            throw new InvalidArgumentException('Money currency must be an ISO 4217 code.');
        }

        return self::of($amount, $currency);
    }

    private function assertSameCurrency(self $other): void
    {
        if (! $this->currency->equals($other->currency)) {
            throw CurrencyMismatch::between($this->currency, $other->currency);
        }
    }
}

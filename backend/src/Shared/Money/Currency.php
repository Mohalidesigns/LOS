<?php

declare(strict_types=1);

namespace Fundly\Shared\Money;

use InvalidArgumentException;

/** ISO 4217 currency code with its minor-unit exponent. */
final readonly class Currency
{
    /** Minor units for the currencies the platform ships with; others default to 2. */
    private const MINOR_UNITS = [
        'NGN' => 2, 'USD' => 2, 'EUR' => 2, 'GBP' => 2, 'XOF' => 0, 'GHS' => 2, 'KES' => 2, 'ZAR' => 2, 'CNY' => 2, 'JPY' => 0,
    ];

    private function __construct(public string $code) {}

    public static function of(string $code): self
    {
        $code = strtoupper(trim($code));
        if (preg_match('/^[A-Z]{3}$/', $code) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid ISO 4217 currency code "%s".', $code));
        }

        return new self($code);
    }

    /** @return int<0, max> */
    public function minorUnits(): int
    {
        return self::MINOR_UNITS[$this->code] ?? 2;
    }

    public function equals(self $other): bool
    {
        return $this->code === $other->code;
    }

    public function __toString(): string
    {
        return $this->code;
    }
}

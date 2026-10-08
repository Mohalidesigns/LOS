<?php

declare(strict_types=1);

namespace Fundly\Shared\Money;

use DomainException;

final class CurrencyMismatch extends DomainException
{
    public static function between(Currency $a, Currency $b): self
    {
        return new self(sprintf('Currency mismatch: %s vs %s.', $a->code, $b->code));
    }
}

<?php

declare(strict_types=1);

namespace Fundly\Shared\Security;

/** Which columns of a list query carry each scope dimension (null = not applicable). */
final readonly class ScopeColumns
{
    public function __construct(
        public ?string $legalEntity = null,
        public ?string $orgUnit = null,
        public ?string $product = null,
        public ?string $currency = null,
        public ?string $amount = null,
        public ?string $segment = null,
    ) {}
}

<?php

declare(strict_types=1);

namespace Fundly\Shared\Security;

use Fundly\Shared\Money\Money;

/**
 * Scope-relevant attributes of a resource (TRD §8.1). A null attribute means
 * the resource does not carry that dimension, so that dimension of an
 * assignment's scope does not constrain access to it.
 */
final readonly class ResourceAttributes
{
    /** @param list<string> $portfolioTags */
    public function __construct(
        public ?string $legalEntityId = null,
        public ?string $orgUnitId = null,
        public ?string $productId = null,
        public ?string $currency = null,
        public ?Money $amount = null,
        public ?string $segment = null,
        public array $portfolioTags = [],
        public ?string $entityType = null,
        public ?string $entityId = null,
    ) {}
}

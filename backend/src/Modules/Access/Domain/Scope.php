<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Domain;

use Fundly\Shared\Money\Money;
use InvalidArgumentException;

/**
 * Scope of a role assignment (FR-SEC-005). Every dimension is optional and
 * they combine with AND; an empty dimension means "unrestricted".
 */
final readonly class Scope
{
    /**
     * @param  list<string>  $legalEntityIds
     * @param  list<string>  $productIds
     * @param  list<string>  $currencies
     * @param  list<string>  $segments
     * @param  list<string>  $portfolioTags
     */
    public function __construct(
        public array $legalEntityIds = [],
        public ?string $orgUnitId = null,
        public array $productIds = [],
        public array $currencies = [],
        public ?Money $maxAmount = null,
        public array $segments = [],
        public array $portfolioTags = [],
    ) {}

    public static function unrestricted(): self
    {
        return new self;
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $list = static function (mixed $v, string $name): array {
            if ($v === null) {
                return [];
            }
            if (! is_array($v) || ! array_is_list($v)) {
                throw new InvalidArgumentException("Scope {$name} must be a list.");
            }
            foreach ($v as $item) {
                if (! is_string($item) || $item === '') {
                    throw new InvalidArgumentException("Scope {$name} must contain non-empty strings.");
                }
            }

            return array_values(array_unique($v));
        };
        $org = $data['org_unit_id'] ?? null;
        $max = $data['max_amount'] ?? null;

        return new self(
            legalEntityIds: $list($data['legal_entity_ids'] ?? null, 'legal_entity_ids'),
            orgUnitId: is_string($org) && $org !== '' ? $org : null,
            productIds: $list($data['product_ids'] ?? null, 'product_ids'),
            currencies: array_map('strtoupper', $list($data['currencies'] ?? null, 'currencies')),
            maxAmount: is_array($max) ? Money::fromArray($max) : null,
            segments: $list($data['segments'] ?? null, 'segments'),
            portfolioTags: $list($data['portfolio_tags'] ?? null, 'portfolio_tags'),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'legal_entity_ids' => $this->legalEntityIds,
            'org_unit_id' => $this->orgUnitId,
            'product_ids' => $this->productIds,
            'currencies' => $this->currencies,
            'max_amount' => $this->maxAmount?->toArray(),
            'segments' => $this->segments,
            'portfolio_tags' => $this->portfolioTags,
        ];
    }

    public function isUnrestricted(): bool
    {
        return $this->legalEntityIds === [] && $this->orgUnitId === null && $this->productIds === []
            && $this->currencies === [] && $this->maxAmount === null && $this->segments === [] && $this->portfolioTags === [];
    }
}

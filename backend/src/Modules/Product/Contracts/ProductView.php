<?php

declare(strict_types=1);

namespace Fundly\Modules\Product\Contracts;

use Brick\Math\BigDecimal;
use Fundly\Shared\Money\Money;

/**
 * One version of a product, as other modules see it. `productId` is stable
 * across versions (scope dimension "product"); `versionId` is what an
 * application pins at creation (FR-PRD-006).
 */
final readonly class ProductView
{
    /**
     * @param  list<string>  $applicantTypes
     * @param  array<string, mixed>  $content  the full validated product definition
     */
    public function __construct(
        public string $productId,
        public string $versionId,
        public string $key,
        public string $name,
        public int $versionNo,
        public string $status,
        public string $category,
        public string $segment,
        public string $currency,
        public Money $amountMin,
        public Money $amountMax,
        public int $tenorMinMonths,
        public int $tenorMaxMonths,
        public array $applicantTypes,
        public array $content,
    ) {}

    public function allowsAmount(Money $amount): bool
    {
        return $amount->currency->code === $this->currency
            && ! $amount->isGreaterThan($this->amountMax)
            && ! $this->amountMin->isGreaterThan($amount);
    }

    public function allowsTenor(int $months): bool
    {
        return $months >= $this->tenorMinMonths && $months <= $this->tenorMaxMonths;
    }

    public function binding(string $kind): ?string
    {
        $bindings = $this->content['bindings'] ?? [];
        $value = is_array($bindings) ? ($bindings[$kind] ?? null) : null;

        return is_string($value) ? $value : null;
    }

    /**
     * Checklist items that apply to an application (FR-PRD-004: conditional on
     * applicant type, segment, channel and amount band).
     *
     * @param  list<string>  $applicantTypes
     * @return list<array{code: string, name: string, mandatory: bool}>
     */
    public function checklistFor(array $applicantTypes, string $channel, Money $amount): array
    {
        $out = [];
        $items = is_array($this->content['checklist'] ?? null) ? $this->content['checklist'] : [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $applies = is_array($item['applies_to'] ?? null) ? $item['applies_to'] : [];
            if (isset($applies['applicant_types']) && is_array($applies['applicant_types']) && array_intersect($applies['applicant_types'], $applicantTypes) === []) {
                continue;
            }
            if (isset($applies['segments']) && is_array($applies['segments']) && ! in_array($this->segment, $applies['segments'], true)) {
                continue;
            }
            if (isset($applies['channels']) && is_array($applies['channels']) && ! in_array($channel, $applies['channels'], true)) {
                continue;
            }
            $value = $amount->amount;
            if (isset($applies['amount_min']) && is_string($applies['amount_min']) && $value->isLessThan(BigDecimal::of($applies['amount_min']))) {
                continue;
            }
            if (isset($applies['amount_max']) && is_string($applies['amount_max']) && $value->isGreaterThan(BigDecimal::of($applies['amount_max']))) {
                continue;
            }
            $out[] = ['code' => (string) ($item['code'] ?? ''), 'name' => (string) ($item['name'] ?? ''), 'mandatory' => (bool) ($item['mandatory'] ?? false)];
        }

        return $out;
    }
}

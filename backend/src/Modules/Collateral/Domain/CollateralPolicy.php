<?php

declare(strict_types=1);

namespace Fundly\Modules\Collateral\Domain;

use Brick\Math\BigDecimal;

/**
 * What a product asks of collateral (FR-COL-002, G-34): whether it is
 * required, the minimum security cover and maximum loan-to-value, and which
 * perfection steps are conditions subsequent rather than precedent.
 *
 *   product.collateral = { required?: bool, min_cover_percent?: "120", max_ltv_percent?: "80",
 *                          perfection_timing?: { step_code: precedent|subsequent } }
 */
final readonly class CollateralPolicy
{
    /** @param array<string, string> $timing */
    private function __construct(public bool $required, public ?BigDecimal $minCoverPercent, public ?BigDecimal $maxLtvPercent, private array $timing) {}

    /** @param array<string, mixed> $productContent */
    public static function fromProduct(array $productContent): self
    {
        $c = is_array($productContent['collateral'] ?? null) ? $productContent['collateral'] : [];
        $timing = [];
        foreach (is_array($c['perfection_timing'] ?? null) ? $c['perfection_timing'] : [] as $code => $t) {
            if (is_string($t) && in_array($t, CollateralType::TIMINGS, true)) {
                $timing[(string) $code] = $t;
            }
        }

        return new self(
            (bool) ($c['required'] ?? false),
            is_string($c['min_cover_percent'] ?? null) ? BigDecimal::of($c['min_cover_percent']) : null,
            is_string($c['max_ltv_percent'] ?? null) ? BigDecimal::of($c['max_ltv_percent']) : null,
            $timing,
        );
    }

    /** The product's timing for a step, else the type's. */
    public function timing(string $stepCode, string $typeTiming): string
    {
        return $this->timing[$stepCode] ?? $typeTiming;
    }

    /** @return array{required: bool, min_cover_percent: ?string, max_ltv_percent: ?string} */
    public function toArray(): array
    {
        return ['required' => $this->required, 'min_cover_percent' => $this->minCoverPercent === null ? null : (string) $this->minCoverPercent, 'max_ltv_percent' => $this->maxLtvPercent === null ? null : (string) $this->maxLtvPercent];
    }
}

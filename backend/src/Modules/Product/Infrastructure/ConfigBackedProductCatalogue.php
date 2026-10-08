<?php

declare(strict_types=1);

namespace Fundly\Modules\Product\Infrastructure;

use Fundly\Modules\Platform\Contracts\ActiveConfiguration;
use Fundly\Modules\Platform\Contracts\ConfigVersionView;
use Fundly\Modules\Product\Contracts\ProductCatalogue;
use Fundly\Modules\Product\Contracts\ProductView;
use Fundly\Shared\Money\Money;

/** Products are `product` configuration artefacts; Platform owns their lifecycle and storage. */
final class ConfigBackedProductCatalogue implements ProductCatalogue
{
    public function __construct(private readonly ActiveConfiguration $config) {}

    public function active(): array
    {
        return array_values(array_filter(array_map($this->map(...), $this->config->activeVersions(self::CONFIG_TYPE))));
    }

    public function activeByKey(string $key): ?ProductView
    {
        $v = $this->config->activeVersion(self::CONFIG_TYPE, $key);

        return $v === null ? null : $this->map($v);
    }

    public function version(string $versionId): ?ProductView
    {
        $v = $this->config->version($versionId);

        return $v === null || $v->type !== self::CONFIG_TYPE ? null : $this->map($v);
    }

    private function map(ConfigVersionView $v): ?ProductView
    {
        $c = $v->content;
        $currency = is_string($c['currency'] ?? null) ? $c['currency'] : null;
        $amount = is_array($c['amount'] ?? null) ? $c['amount'] : [];
        $tenor = is_array($c['tenor_months'] ?? null) ? $c['tenor_months'] : [];
        if ($currency === null || ! is_string($amount['min'] ?? null) || ! is_string($amount['max'] ?? null)) {
            return null;
        }
        $types = is_array($c['applicant_types'] ?? null) ? array_values(array_filter($c['applicant_types'], 'is_string')) : [];

        return new ProductView(
            productId: $v->artifactId,
            versionId: $v->id,
            key: $v->key,
            name: $v->name,
            versionNo: $v->versionNo,
            status: $v->status,
            category: (string) ($c['category'] ?? ''),
            segment: (string) ($c['segment'] ?? ''),
            currency: $currency,
            amountMin: Money::of($amount['min'], $currency),
            amountMax: Money::of($amount['max'], $currency),
            tenorMinMonths: is_int($tenor['min'] ?? null) ? $tenor['min'] : 1,
            tenorMaxMonths: is_int($tenor['max'] ?? null) ? $tenor['max'] : 1,
            applicantTypes: $types,
            content: $c,
        );
    }
}

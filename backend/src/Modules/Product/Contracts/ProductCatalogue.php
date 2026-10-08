<?php

declare(strict_types=1);

namespace Fundly\Modules\Product\Contracts;

/** The tenant's product catalogue: active versions for new business, pinned versions for in-flight work. */
interface ProductCatalogue
{
    public const CONFIG_TYPE = 'product';

    /** @return list<ProductView> */
    public function active(): array;

    public function activeByKey(string $key): ?ProductView;

    /** The exact version an application is pinned to, even after it is superseded (FR-PRD-006). */
    public function version(string $versionId): ?ProductView;
}

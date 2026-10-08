<?php

declare(strict_types=1);

namespace Fundly\Modules\Product\Http\Controllers;

use Fundly\Modules\Product\Application\ProductPresenter;
use Fundly\Modules\Product\Contracts\ProductCatalogue;
use Fundly\Shared\Exceptions\NotFound;
use Fundly\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Read-only product catalogue for capture (FR-PRD-001). Authoring, review and
 * activation run through /config-artifacts/product (FR-TEN-009, D-040a).
 */
final class ProductController
{
    public function __construct(private readonly ProductCatalogue $catalogue) {}

    public function index(): JsonResponse
    {
        return ApiResponse::json(['data' => array_map(ProductPresenter::summary(...), $this->catalogue->active())]);
    }

    public function show(string $key): JsonResponse
    {
        $product = $this->catalogue->activeByKey($key) ?? throw new NotFound("Product {$key} has no active version.");

        return ApiResponse::json(['data' => ProductPresenter::detail($product)]);
    }
}

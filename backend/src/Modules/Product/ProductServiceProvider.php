<?php

declare(strict_types=1);

namespace Fundly\Modules\Product;

use Fundly\Modules\Platform\Contracts\ConfigTypeCatalogue;
use Fundly\Modules\Product\Contracts\ProductCatalogue;
use Fundly\Modules\Product\Domain\ProductDefinition;
use Fundly\Modules\Product\Infrastructure\ConfigBackedProductCatalogue;
use Illuminate\Support\ServiceProvider;

/** M03 Product: product factory on top of Platform's versioned configuration (FR-PRD-001..006). */
final class ProductServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(ProductCatalogue::class, ConfigBackedProductCatalogue::class);
    }

    public function boot(ConfigTypeCatalogue $types): void
    {
        $types->register(
            ProductCatalogue::CONFIG_TYPE,
            'A loan product: terms, fees, eligibility, checklist and bound workflow / rule set / approval matrix (FR-PRD-001..006).',
            static function (array $content): void {
                ProductDefinition::validate($content);
            },
        );
    }
}

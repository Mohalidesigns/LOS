<?php

declare(strict_types=1);

namespace Fundly\Modules\Collateral;

use Fundly\Modules\Access\Application\ChangeRequests\ChangeActionRegistry;
use Fundly\Modules\Application\Contracts\ApplicationReader;
use Fundly\Modules\Collateral\Application\CollateralFile;
use Fundly\Modules\Collateral\Application\SecurityAssessor;
use Fundly\Modules\Collateral\Application\WaiveCollateralAction;
use Fundly\Modules\Collateral\Contracts\CollateralRegister;
use Fundly\Modules\Collateral\Domain\CollateralType;
use Fundly\Modules\Collateral\Infrastructure\DatabaseCollateralRegister;
use Fundly\Modules\Collateral\Infrastructure\Models\Collateral;
use Fundly\Modules\Collateral\Infrastructure\Models\PerfectionStep;
use Fundly\Modules\Platform\Contracts\ConfigTypeCatalogue;
use Fundly\Shared\Security\ResourceAttributes;
use Fundly\Shared\Security\ResourceResolver;
use Illuminate\Support\ServiceProvider;

/** M09 Collateral: types, cover, valuations, perfection, allocation, insurance, disbursement block (FR-COL-001..006, 008). */
final class CollateralServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(CollateralFile::class);
        $this->app->scoped(SecurityAssessor::class);
        $this->app->scoped(CollateralRegister::class, DatabaseCollateralRegister::class);
    }

    public function boot(ResourceResolver $resources, ConfigTypeCatalogue $types): void
    {
        $types->register(
            CollateralFile::TYPE,
            'Collateral type (key = type code): category, haircut, valuation validity, insurance requirement and the perfection checklist, by state where it differs (FR-COL-001..004, G-34).',
            static function (array $content): void {
                CollateralType::validate($content);
            },
        );
        $this->app->make(ChangeActionRegistry::class)->register(WaiveCollateralAction::TYPE, WaiveCollateralAction::class);
        $app = $this->app;
        $attributes = static function (?string $applicationId, string $type, string $id) use ($app): ?ResourceAttributes {
            $a = $applicationId === null ? null : $app->make(ApplicationReader::class)->find($applicationId);

            return $a === null ? null : new ResourceAttributes(legalEntityId: $a->legalEntityId, orgUnitId: $a->orgUnitId, productId: $a->productId, segment: $a->segment, entityType: $type, entityId: $id);
        };
        $uuid = static fn (string $id): bool => preg_match('/^[0-9a-f-]{36}$/', $id) === 1;
        $resources->register('collateral', static function (string $id) use ($attributes, $uuid): ?ResourceAttributes {
            $c = $uuid($id) ? Collateral::query()->find($id) : null;

            return $attributes($c?->origin_application_id, 'collateral', $id);
        });
        $resources->register('perfection_step', static function (string $id) use ($attributes, $uuid): ?ResourceAttributes {
            $s = $uuid($id) ? PerfectionStep::query()->find($id) : null;
            $c = $s === null ? null : Collateral::query()->find($s->collateral_id);

            return $attributes($c?->origin_application_id, 'perfection_step', $id);
        });
    }
}

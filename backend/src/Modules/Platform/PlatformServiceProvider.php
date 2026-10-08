<?php

declare(strict_types=1);

namespace Fundly\Modules\Platform;

use Fundly\Modules\Access\Application\ChangeRequests\ChangeActionRegistry;
use Fundly\Modules\Platform\Application\Config\ActivateConfigVersionAction;
use Fundly\Modules\Platform\Application\Config\ConfigTypeRegistry;
use Fundly\Modules\Platform\Console\ProvisionTenantCommand;
use Fundly\Modules\Platform\Contracts\ActiveConfiguration;
use Fundly\Modules\Platform\Contracts\ConfigTypeCatalogue;
use Fundly\Modules\Platform\Contracts\OrganisationDirectory;
use Fundly\Modules\Platform\Contracts\TenantDirectory;
use Fundly\Modules\Platform\Infrastructure\DatabaseActiveConfiguration;
use Fundly\Modules\Platform\Infrastructure\DatabaseOrganisationDirectory;
use Fundly\Modules\Platform\Infrastructure\DatabaseTenantDirectory;
use Fundly\Modules\Platform\Infrastructure\Models\ConfigVersion;
use Fundly\Modules\Platform\Infrastructure\Models\OrgUnit;
use Fundly\Shared\Security\ResourceAttributes;
use Fundly\Shared\Security\ResourceResolver;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

final class PlatformServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantDirectory::class, fn (Application $app) => new DatabaseTenantDirectory($app->make('db')->connection(), (string) config('fundly.tenancy.resolution', 'single')));
        $this->app->scoped(ActiveConfiguration::class, DatabaseActiveConfiguration::class);
        $this->app->scoped(OrganisationDirectory::class, DatabaseOrganisationDirectory::class);
        $this->app->singleton(ConfigTypeRegistry::class);
        $this->app->alias(ConfigTypeRegistry::class, ConfigTypeCatalogue::class);
    }

    public function boot(ResourceResolver $resources): void
    {
        // The Access module owns the maker-checker engine; Platform registers its action through it.
        $this->app->make(ChangeActionRegistry::class)->register(ActivateConfigVersionAction::TYPE, ActivateConfigVersionAction::class);

        $resources->register('legal_entity', static fn (string $id): ?ResourceAttributes => DB::table('legal_entities')->where('id', $id)->exists()
            ? new ResourceAttributes(legalEntityId: $id, entityType: 'legal_entity', entityId: $id) : null);
        $resources->register('org_unit', static function (string $id): ?ResourceAttributes {
            $ou = OrgUnit::query()->find($id);

            return $ou === null ? null : new ResourceAttributes(legalEntityId: $ou->legal_entity_id, orgUnitId: $ou->id, entityType: 'org_unit', entityId: $id);
        });
        $resources->register('config_artifact', static fn (string $id): ?ResourceAttributes => DB::table('config_artifacts')->where('id', $id)->exists() ? new ResourceAttributes(entityType: 'config_artifact', entityId: $id) : null);
        $resources->register('config_version', static fn (string $id): ?ResourceAttributes => ConfigVersion::query()->whereKey($id)->exists() ? new ResourceAttributes(entityType: 'config_version', entityId: $id) : null);

        if ($this->app->runningInConsole()) {
            $this->commands([ProvisionTenantCommand::class]);
        }
    }
}

<?php

declare(strict_types=1);

namespace Fundly\Modules\Application;

use Fundly\Modules\Application\Application\ApplicationQueries;
use Fundly\Modules\Application\Application\BusApplicationLifecycle;
use Fundly\Modules\Application\Console\RebuildProjectionCommand;
use Fundly\Modules\Application\Contracts\ApplicationLifecycle;
use Fundly\Modules\Application\Contracts\ApplicationReader;
use Fundly\Modules\Application\Infrastructure\Models\ApplicationRecord;
use Fundly\Modules\Application\Infrastructure\StoreBackedApplicationReader;
use Fundly\Shared\Security\ResourceAttributes;
use Fundly\Shared\Security\ResourceResolver;
use Illuminate\Support\ServiceProvider;

/** M06 Application: event-sourced aggregate, projection, references, provenance. */
final class ApplicationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(ApplicationReader::class, StoreBackedApplicationReader::class);
        $this->app->scoped(ApplicationLifecycle::class, BusApplicationLifecycle::class);
    }

    public function boot(ResourceResolver $resources): void
    {
        $resources->register('application', static function (string $id): ?ResourceAttributes {
            $r = preg_match('/^[0-9a-f-]{36}$/', $id) === 1 ? ApplicationRecord::query()->find($id) : null;

            return $r === null ? null : ApplicationQueries::attributes($r);
        });
        if ($this->app->runningInConsole()) {
            $this->commands([RebuildProjectionCommand::class]);
        }
    }
}

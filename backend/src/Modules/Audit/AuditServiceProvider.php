<?php

declare(strict_types=1);

namespace Fundly\Modules\Audit;

use Fundly\Modules\Audit\Application\AuditCheckpointer;
use Fundly\Modules\Audit\Console\AuditCheckpointCommand;
use Fundly\Modules\Audit\Console\AuditVerifyCommand;
use Fundly\Shared\Clock\Clock;
use Fundly\Shared\SharedServiceProvider;
use Fundly\Shared\Tenancy\TenantContext;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class AuditServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(AuditCheckpointer::class, function (Application $app): AuditCheckpointer {
            $path = config('fundly.audit.checkpoint_path');

            return new AuditCheckpointer($app->make('db')->connection(), $app->make(TenantContext::class), $app->make(Clock::class), is_string($path) && $path !== '' ? SharedServiceProvider::absolutePath($path) : null);
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([AuditVerifyCommand::class, AuditCheckpointCommand::class]);
        }
    }
}

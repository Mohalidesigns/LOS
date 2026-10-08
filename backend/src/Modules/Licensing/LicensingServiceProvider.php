<?php

declare(strict_types=1);

namespace Fundly\Modules\Licensing;

use Fundly\Integration\Adapters\OfflineLicence\InstallationIdentity;
use Fundly\Integration\Adapters\OfflineLicence\OfflineSignedFileLicensing;
use Fundly\Integration\Ports\Licensing\LicensingPort;
use Fundly\Modules\Access\Application\ChangeRequests\ChangeActionRegistry;
use Fundly\Modules\Licensing\Application\LicenceGuard;
use Fundly\Modules\Licensing\Application\LicenceImportAction;
use Fundly\Modules\Licensing\Console\LicenceCheckCommand;
use Fundly\Modules\Licensing\Console\LicenceImportCommand;
use Fundly\Modules\Licensing\Console\LicenceIssueCommand;
use Fundly\Modules\Licensing\Console\LicenceKeypairCommand;
use Fundly\Modules\Licensing\Contracts\LicenceEntitlements;
use Fundly\Shared\Clock\Clock;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class LicensingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(InstallationIdentity::class, fn (Application $app) => new InstallationIdentity($app->make('db')->connection(), (string) config('fundly.installation.environment')));
        $this->app->scoped(LicensingPort::class, fn (Application $app) => new OfflineSignedFileLicensing(
            $app->make('db')->connection(),
            $app->make(InstallationIdentity::class),
            $app->make(Clock::class),
            (string) config('fundly.licence.public_key', ''),
        ));
        $this->app->scoped(LicenceGuard::class);
        $this->app->scoped(LicenceEntitlements::class, LicenceGuard::class);
    }

    public function boot(): void
    {
        $this->app->make(ChangeActionRegistry::class)->register($this->app->make(LicenceImportAction::class));
        if ($this->app->runningInConsole()) {
            $this->commands([LicenceKeypairCommand::class, LicenceIssueCommand::class, LicenceImportCommand::class, LicenceCheckCommand::class]);
        }
    }
}

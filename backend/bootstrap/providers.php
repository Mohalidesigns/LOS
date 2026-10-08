<?php

declare(strict_types=1);

return [
    App\Providers\AppServiceProvider::class,
    Fundly\Shared\SharedServiceProvider::class,
    Fundly\Modules\Access\AccessServiceProvider::class,
    Fundly\Modules\Platform\PlatformServiceProvider::class,
    Fundly\Modules\Audit\AuditServiceProvider::class,
    Fundly\Modules\Licensing\LicensingServiceProvider::class,
    Fundly\Integration\IntegrationServiceProvider::class,
];

<?php

declare(strict_types=1);
use App\Providers\AppServiceProvider;
use Fundly\Integration\IntegrationServiceProvider;
use Fundly\Modules\Access\AccessServiceProvider;
use Fundly\Modules\Application\ApplicationServiceProvider;
use Fundly\Modules\Audit\AuditServiceProvider;
use Fundly\Modules\Licensing\LicensingServiceProvider;
use Fundly\Modules\Party\PartyServiceProvider;
use Fundly\Modules\Platform\PlatformServiceProvider;
use Fundly\Modules\Product\ProductServiceProvider;
use Fundly\Shared\SharedServiceProvider;

return [
    AppServiceProvider::class,
    SharedServiceProvider::class,
    AccessServiceProvider::class,
    PlatformServiceProvider::class,
    AuditServiceProvider::class,
    LicensingServiceProvider::class,
    IntegrationServiceProvider::class,
    ProductServiceProvider::class,
    PartyServiceProvider::class,
    ApplicationServiceProvider::class,
];

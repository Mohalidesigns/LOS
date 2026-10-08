<?php

declare(strict_types=1);

namespace Fundly\Integration\Runtime\Outbox;

use Fundly\Modules\Licensing\Contracts\LicenceFailSafe;
use Fundly\Modules\Platform\Contracts\TenantDirectory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Scheduled every few seconds by the scheduler; runs as system:outbox.
 * Delivers in-flight external effects, so it is licence fail-safe (D-034):
 * an expired licence never strands a saga half way.
 */
final class DispatchOutboxJob implements ShouldQueue, ShouldBeUnique, LicenceFailSafe
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $uniqueFor = 60;

    public function handle(OutboxDispatcher $dispatcher, TenantDirectory $tenants): void
    {
        $batch = (int) config('fundly.integration.outbox.batch_size', 50);
        foreach ($tenants->activeTenantIds() as $tenantId) {
            $dispatcher->dispatchDue($tenantId, $batch);
        }
    }
}

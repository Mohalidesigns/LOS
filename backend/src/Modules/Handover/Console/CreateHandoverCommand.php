<?php

declare(strict_types=1);

namespace Fundly\Modules\Handover\Console;

use Fundly\Modules\Application\Contracts\CanonicalStatus;
use Fundly\Modules\Handover\Application\Commands\CreateHandoverPackage;
use Fundly\Modules\Platform\Contracts\TenantDirectory;
use Fundly\Shared\Bus\CommandBus;
use Fundly\Shared\Security\Principal;
use Fundly\Shared\Security\SystemIdentity;
use Fundly\Shared\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/** Catch-up: hand over booked applications that have no package (booked before M15, or the listener failed). Idempotent. */
final class CreateHandoverCommand extends Command
{
    protected $signature = 'handover:create';

    protected $description = 'Create the servicing handover package for booked applications that do not have one.';

    public function handle(TenantDirectory $tenants, TenantContext $tenant, CommandBus $bus): int
    {
        $total = 0;
        foreach ($tenants->activeTenantIds() as $tenantId) {
            $tenant->run($tenantId, function () use ($tenantId, $bus, &$total): void {
                $ids = DB::table('applications')->where('canonical_status', CanonicalStatus::Booked->value)
                    ->whereNotIn('id', DB::table('handover_packages')->select('application_id'))->pluck('id');
                foreach ($ids as $id) {
                    try {
                        $bus->dispatch(new CreateHandoverPackage((string) $id), Principal::system($tenantId, SystemIdentity::Workflow));
                        $total++;
                    } catch (Throwable $e) {
                        $this->error("Application {$id}: {$e->getMessage()}");
                    }
                }
            });
        }
        $this->info("{$total} handover package(s) created.");

        return self::SUCCESS;
    }
}

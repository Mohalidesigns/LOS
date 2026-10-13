<?php

declare(strict_types=1);

namespace Fundly\Modules\Conditions\Console;

use Fundly\Modules\Application\Contracts\CanonicalStatus;
use Fundly\Modules\Conditions\Application\Commands\ImportConditions;
use Fundly\Modules\Platform\Contracts\TenantDirectory;
use Fundly\Shared\Bus\CommandBus;
use Fundly\Shared\Security\Principal;
use Fundly\Shared\Security\SystemIdentity;
use Fundly\Shared\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Catch-up: open the condition register for applications that reached
 * Accepted without it (accepted before M13 was installed, or the listener
 * failed). Safe to re-run: approval conditions are imported once.
 */
final class OpenConditionsCommand extends Command
{
    protected $signature = 'conditions:open';

    protected $description = 'Open the CP/CS register for accepted applications that do not have one yet.';

    public function handle(TenantDirectory $tenants, TenantContext $tenant, CommandBus $bus): int
    {
        $total = 0;
        foreach ($tenants->activeTenantIds() as $tenantId) {
            $tenant->run($tenantId, function () use ($tenantId, $bus, &$total): void {
                foreach (DB::table('applications')->where('canonical_status', CanonicalStatus::Accepted->value)->pluck('id') as $id) {
                    try {
                        $bus->dispatch(new ImportConditions((string) $id), Principal::system($tenantId, SystemIdentity::Workflow));
                        $total++;
                    } catch (Throwable $e) {
                        $this->error("Application {$id}: {$e->getMessage()}");
                    }
                }
            });
        }
        $this->info("{$total} application(s) moved to conditions precedent.");

        return self::SUCCESS;
    }
}

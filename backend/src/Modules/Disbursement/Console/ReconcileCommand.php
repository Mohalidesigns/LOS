<?php

declare(strict_types=1);

namespace Fundly\Modules\Disbursement\Console;

use Fundly\Modules\Disbursement\Application\Commands\RunReconciliation;
use Fundly\Modules\Platform\Contracts\TenantDirectory;
use Fundly\Shared\Bus\CommandBus;
use Fundly\Shared\Security\Principal;
use Fundly\Shared\Security\SystemIdentity;
use Fundly\Shared\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Throwable;

/** Daily: reconcile yesterday and today's disbursement postings with the CBA (FR-DSB-008). */
final class ReconcileCommand extends Command
{
    protected $signature = 'reconciliation:run {--from=} {--to=}';

    protected $description = 'Reconcile LOS disbursement postings with the core banking system.';

    public function handle(TenantDirectory $tenants, TenantContext $tenant, CommandBus $bus): int
    {
        $failed = 0;
        foreach ($tenants->activeTenantIds() as $tenantId) {
            $tenant->run($tenantId, function () use ($tenantId, $bus, &$failed): void {
                try {
                    $from = $this->option('from');
                    $to = $this->option('to');
                    /** @var array{data: array{breaks: int, matched: int}} $r */
                    $r = $bus->dispatch(new RunReconciliation(is_string($from) ? $from : null, is_string($to) ? $to : null), Principal::system($tenantId, SystemIdentity::Scheduler));
                    $this->info("Tenant {$tenantId}: {$r['data']['matched']} matched, {$r['data']['breaks']} break(s).");
                } catch (Throwable $e) {
                    $failed++;
                    $this->error("Tenant {$tenantId}: {$e->getMessage()}");
                }
            });
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}

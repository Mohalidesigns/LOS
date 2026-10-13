<?php

declare(strict_types=1);

namespace Fundly\Modules\Approval\Console;

use Fundly\Modules\Approval\Application\Commands\ExpireApproval;
use Fundly\Modules\Approval\Infrastructure\Models\ApprovalRequest;
use Fundly\Modules\Platform\Contracts\TenantDirectory;
use Fundly\Shared\Bus\CommandBus;
use Fundly\Shared\Clock\Clock;
use Fundly\Shared\Security\Principal;
use Fundly\Shared\Security\SystemIdentity;
use Fundly\Shared\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

/** Hourly: approvals past their validity expire instead of lapsing silently into disbursement (FR-APV-012). */
final class ExpireApprovalsCommand extends Command
{
    protected $signature = 'approvals:expire';

    protected $description = 'Expire approvals whose validity has lapsed.';

    public function handle(TenantDirectory $tenants, TenantContext $tenant, Clock $clock, CommandBus $bus): int
    {
        $total = 0;
        $failed = 0;
        foreach ($tenants->activeTenantIds() as $tenantId) {
            $tenant->run($tenantId, function () use ($tenantId, $clock, $bus, &$total, &$failed): void {
                $due = ApprovalRequest::query()->where('status', 'approved')->where('valid_until', '<=', Carbon::instance($clock->now()))->pluck('id');
                foreach ($due as $id) {
                    try {
                        $bus->dispatch(new ExpireApproval((string) $id), Principal::system($tenantId, SystemIdentity::Scheduler));
                        $total++;
                    } catch (Throwable $e) {
                        $failed++;
                        $this->error("Approval {$id}: {$e->getMessage()}");
                    }
                }
            });
        }
        $this->info("{$total} approval(s) expired.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}

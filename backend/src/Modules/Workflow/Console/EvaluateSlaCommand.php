<?php

declare(strict_types=1);

namespace Fundly\Modules\Workflow\Console;

use Fundly\Modules\Platform\Contracts\TenantDirectory;
use Fundly\Modules\Workflow\Application\Commands\EvaluateSlaClocks;
use Fundly\Shared\Bus\CommandBus;
use Fundly\Shared\Security\Principal;
use Fundly\Shared\Security\SystemIdentity;
use Fundly\Shared\Tenancy\TenantContext;
use Illuminate\Console\Command;

/** Every minute (TRD §6.5): move SLA clocks and escalate. */
final class EvaluateSlaCommand extends Command
{
    protected $signature = 'workflow:sla';

    protected $description = 'Evaluate stage SLA clocks: warn owners, escalate breaches.';

    public function handle(TenantDirectory $tenants, TenantContext $tenant, CommandBus $bus): int
    {
        $total = ['at_risk' => 0, 'breached' => 0];
        foreach ($tenants->activeTenantIds() as $tenantId) {
            /** @var array{at_risk: int, breached: int} $r */
            $r = $tenant->run($tenantId, static fn (): array => $bus->dispatch(new EvaluateSlaClocks, Principal::system($tenantId, SystemIdentity::Workflow)));
            $total['at_risk'] += $r['at_risk'];
            $total['breached'] += $r['breached'];
        }
        $this->info("{$total['at_risk']} task(s) at risk, {$total['breached']} breached.");

        return self::SUCCESS;
    }
}

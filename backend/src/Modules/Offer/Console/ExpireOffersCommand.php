<?php

declare(strict_types=1);

namespace Fundly\Modules\Offer\Console;

use Fundly\Modules\Offer\Application\Commands\ExpireOffer;
use Fundly\Modules\Offer\Infrastructure\Models\Offer;
use Fundly\Modules\Platform\Contracts\TenantDirectory;
use Fundly\Shared\Bus\CommandBus;
use Fundly\Shared\Clock\Clock;
use Fundly\Shared\Security\Principal;
use Fundly\Shared\Security\SystemIdentity;
use Fundly\Shared\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

/** Hourly: offers not accepted within their validity expire (FR-OFR-007). */
final class ExpireOffersCommand extends Command
{
    protected $signature = 'offers:expire';

    protected $description = 'Expire issued offers whose validity has lapsed.';

    public function handle(TenantDirectory $tenants, TenantContext $tenant, Clock $clock, CommandBus $bus): int
    {
        $total = 0;
        $failed = 0;
        foreach ($tenants->activeTenantIds() as $tenantId) {
            $tenant->run($tenantId, function () use ($tenantId, $clock, $bus, &$total, &$failed): void {
                foreach (Offer::query()->where('status', 'issued')->where('valid_until', '<=', Carbon::instance($clock->now()))->pluck('id') as $id) {
                    try {
                        $bus->dispatch(new ExpireOffer((string) $id), Principal::system($tenantId, SystemIdentity::Scheduler));
                        $total++;
                    } catch (Throwable $e) {
                        $failed++;
                        $this->error("Offer {$id}: {$e->getMessage()}");
                    }
                }
            });
        }
        $this->info("{$total} offer(s) expired.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}

<?php

declare(strict_types=1);

namespace Fundly\Modules\Notification\Console;

use DateInterval;
use Fundly\Modules\Notification\Application\NotificationTriggers;
use Fundly\Modules\Offer\Contracts\OfferRegister;
use Fundly\Modules\Platform\Contracts\TenantDirectory;
use Fundly\Shared\Clock\Clock;
use Fundly\Shared\Tenancy\TenantContext;
use Illuminate\Console\Command;

/** Daily: remind customers whose offer lapses within the reminder window (FR-OFR-007); once per offer. */
final class SendRemindersCommand extends Command
{
    protected $signature = 'notifications:reminders';

    protected $description = 'Send offer expiry reminders.';

    public function handle(TenantDirectory $tenants, TenantContext $tenant, OfferRegister $offers, Clock $clock, NotificationTriggers $triggers): int
    {
        $days = max(1, (int) config('fundly.notifications.offer_reminder_days', 3));
        $now = $clock->now();
        $total = 0;
        foreach ($tenants->activeTenantIds() as $tenantId) {
            $list = $tenant->run($tenantId, static fn (): array => $offers->expiring($now, $now->add(new DateInterval("P{$days}D"))));
            foreach ($list as $o) {
                $triggers->notify($tenantId, 'offer.expiring', $o->applicationId, $o->offerId, ['valid_until' => $o->validUntil->format('Y-m-d'), 'offer_sequence' => $o->sequence]);
                $total++;
            }
        }
        $this->info("{$total} offer reminder(s) considered.");

        return self::SUCCESS;
    }
}

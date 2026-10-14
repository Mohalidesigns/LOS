<?php

declare(strict_types=1);

namespace Fundly\Modules\Notification\Console;

use DateInterval;
use Fundly\Modules\Collateral\Contracts\CollateralRegister;
use Fundly\Modules\Notification\Application\NotificationTriggers;
use Fundly\Modules\Offer\Contracts\OfferRegister;
use Fundly\Modules\Platform\Contracts\TenantDirectory;
use Fundly\Shared\Clock\Clock;
use Fundly\Shared\Tenancy\TenantContext;
use Illuminate\Console\Command;

/**
 * Daily: remind customers whose offer lapses within the reminder window
 * (FR-OFR-007), and collateral officers of valuations and insurance policies
 * lapsing within the collateral notice window (FR-COL-003/006). Once each.
 */
final class SendRemindersCommand extends Command
{
    protected $signature = 'notifications:reminders';

    protected $description = 'Send offer expiry, collateral re-valuation and insurance expiry reminders.';

    public function handle(TenantDirectory $tenants, TenantContext $tenant, OfferRegister $offers, CollateralRegister $collateral, Clock $clock, NotificationTriggers $triggers): int
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
        $notice = max(1, (int) config('fundly.collateral.notice_days', 30));
        $prompts = 0;
        foreach ($tenants->activeTenantIds() as $tenantId) {
            $list = $tenant->run($tenantId, static fn (): array => $collateral->attentionDue($now->add(new DateInterval("P{$notice}D"))));
            foreach ($list as $a) {
                $event = $a->kind === 'revaluation' ? 'collateral.revaluation_due' : 'collateral.insurance_expiring';
                $triggers->notify($tenantId, $event, $a->applicationId, $a->sourceId, ['collateral' => $a->description, 'due_date' => $a->dueDate->format('Y-m-d')]);
                $prompts++;
            }
        }
        $this->info("{$total} offer reminder(s) and {$prompts} collateral prompt(s) considered.");

        return self::SUCCESS;
    }
}

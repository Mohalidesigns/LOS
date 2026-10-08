<?php

declare(strict_types=1);

namespace Fundly\Modules\Licensing\Console;

use Fundly\Integration\Ports\Licensing\LicenceState;
use Fundly\Modules\Licensing\Application\LicenceGuard;
use Fundly\Modules\Platform\Contracts\TenantDirectory;
use Fundly\Shared\Audit\Actor;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Audit\AuditTrail;
use Fundly\Shared\Clock\Clock;
use Fundly\Shared\Id\UuidV7;
use Fundly\Shared\Security\SystemIdentity;
use Fundly\Shared\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Daily: raises expiry warnings at T-60/T-30/T-7, entering grace and expiry
 * (TRD §2.6) as licence events and audit events in every tenant's chain.
 */
final class LicenceCheckCommand extends Command
{
    protected $signature = 'licence:check';

    protected $description = 'Evaluate the licence and raise expiry warnings.';

    public function handle(LicenceGuard $guard, Clock $clock, TenantDirectory $tenants, TenantContext $tenant, AuditTrail $audit): int
    {
        $licence = $guard->licence();
        $state = $guard->state();
        $now = $clock->now();
        $event = null;
        if ($licence !== null) {
            $days = $licence->daysUntilExpiry($now);
            foreach ((array) config('fundly.licence.warning_days', [60, 30, 7]) as $threshold) {
                if ($state === LicenceState::Valid && $days === (int) $threshold) {
                    $event = 'expiry_warning_t_minus_'.$threshold;
                }
            }
            $event = match ($state) {
                LicenceState::Grace => 'in_grace',
                LicenceState::Expired => 'expired',
                default => $event,
            };
        } else {
            $event = 'not_installed';
        }

        $this->line('Licence state: '.$state->value.($event !== null ? " ({$event})" : ''));
        if ($event === null) {
            return self::SUCCESS;
        }
        Log::warning('licence.'.$event, ['licence_id' => $licence?->licenceId]);
        DB::table('licence_events')->insert([
            'id' => UuidV7::generate(), 'licence_id' => $licence?->licenceId, 'event' => $event,
            'details' => json_encode(['state' => $state->value], JSON_THROW_ON_ERROR), 'occurred_at' => $now,
        ]);
        foreach ($tenants->activeTenantIds() as $id) {
            $tenant->run($id, fn () => $audit->record(new AuditEntry(action: 'licensing.licence.'.$event, entityType: 'licence', entityId: $licence?->licenceId, after: ['state' => $state->value]), Actor::system(SystemIdentity::Licensing)));
        }

        return self::SUCCESS;
    }
}

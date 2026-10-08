<?php

declare(strict_types=1);

namespace Fundly\Modules\Compliance;

use Fundly\Integration\Runtime\Outbox\OutboxHandlerRegistry;
use Fundly\Modules\Application\Contracts\Events\ApplicationStatusChanged;
use Fundly\Modules\Compliance\Application\KycProgression;
use Fundly\Modules\Compliance\Contracts\Events\ApplicationScreened;
use Fundly\Modules\Compliance\Contracts\Events\ScreeningAlertResolved;
use Fundly\Modules\Compliance\Infrastructure\Models\ScreeningAlert;
use Fundly\Modules\Compliance\Infrastructure\ScreenApplicationOutboxHandler;
use Fundly\Modules\Party\Contracts\Events\PartyKycChanged;
use Fundly\Shared\Security\ResourceAttributes;
use Fundly\Shared\Security\ResourceResolver;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;

/** M18 Compliance (MVP slice): intake screening, four-eyes alert disposition, CDD progression gate. */
final class ComplianceServiceProvider extends ServiceProvider
{
    public function boot(ResourceResolver $resources, Dispatcher $events, OutboxHandlerRegistry $outbox): void
    {
        $resources->register('screening_alert', static function (string $id): ?ResourceAttributes {
            $a = preg_match('/^[0-9a-f-]{36}$/', $id) === 1 ? ScreeningAlert::query()->find($id) : null;

            return $a === null ? null : new ResourceAttributes(orgUnitId: $a->org_unit_id, entityType: 'screening_alert', entityId: $a->id);
        });
        $outbox->register(KycProgression::SCREEN_TOPIC, ScreenApplicationOutboxHandler::class);

        $events->listen(ApplicationStatusChanged::class, [KycProgression::class, 'onStatusChanged']);
        $events->listen(ApplicationScreened::class, [KycProgression::class, 'onScreened']);
        $events->listen(ScreeningAlertResolved::class, [KycProgression::class, 'onAlertResolved']);
        $events->listen(PartyKycChanged::class, [KycProgression::class, 'onPartyKycChanged']);
    }
}

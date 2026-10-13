<?php

declare(strict_types=1);

namespace Fundly\Modules\Handover;

use Fundly\Modules\Application\Contracts\ApplicationReader;
use Fundly\Modules\Disbursement\Contracts\Events\FacilityBooked;
use Fundly\Modules\Handover\Application\HandoverProgression;
use Fundly\Modules\Handover\Console\CreateHandoverCommand;
use Fundly\Modules\Handover\Infrastructure\Models\HandoverPackage;
use Fundly\Shared\Security\ResourceAttributes;
use Fundly\Shared\Security\ResourceResolver;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;

/** M15 Handover: package to servicing on booking, facility.created webhook, acknowledgement, retention (FR-HND-001/003/004). */
final class HandoverServiceProvider extends ServiceProvider
{
    public function boot(ResourceResolver $resources, Dispatcher $events): void
    {
        $app = $this->app;
        $resources->register('handover_package', static function (string $id) use ($app): ?ResourceAttributes {
            $p = preg_match('/^[0-9a-f-]{36}$/', $id) === 1 ? HandoverPackage::query()->find($id) : null;
            $a = $p === null ? null : $app->make(ApplicationReader::class)->find($p->application_id);

            return $a === null || $p === null ? null : new ResourceAttributes(legalEntityId: $a->legalEntityId, orgUnitId: $a->orgUnitId, productId: $a->productId, segment: $a->segment, entityType: 'handover_package', entityId: $p->id);
        });
        $events->listen(FacilityBooked::class, [HandoverProgression::class, 'onFacilityBooked']);
        if ($this->app->runningInConsole()) {
            $this->commands([CreateHandoverCommand::class]);
        }
    }
}

<?php

declare(strict_types=1);

namespace Fundly\Modules\Handover\Application;

use Fundly\Modules\Disbursement\Contracts\Events\FacilityBooked;
use Fundly\Modules\Handover\Application\Commands\CreateHandoverPackage;
use Fundly\Shared\Bus\CommandBus;
use Fundly\Shared\Security\Principal;
use Fundly\Shared\Security\SystemIdentity;
use Fundly\Shared\Tenancy\TenantContext;
use Psr\Log\LoggerInterface;
use Throwable;

/** Booked → handover package + facility.created (demo step 17). A failure is logged; `handover:create` retries it. */
final class HandoverProgression
{
    public function __construct(private readonly CommandBus $bus, private readonly TenantContext $tenant, private readonly LoggerInterface $log) {}

    public function onFacilityBooked(FacilityBooked $e): void
    {
        $this->tenant->run($e->tenantId, function () use ($e): void {
            try {
                $this->bus->dispatch(new CreateHandoverPackage($e->applicationId), Principal::system($e->tenantId, SystemIdentity::Workflow));
            } catch (Throwable $ex) {
                $this->log->error('Handover failed', ['application_id' => $e->applicationId, 'error' => $ex->getMessage()]);
            }
        });
    }
}

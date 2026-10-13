<?php

declare(strict_types=1);

namespace Fundly\Modules\Conditions\Application;

use Fundly\Modules\Conditions\Application\Commands\ImportConditions;
use Fundly\Modules\Offer\Contracts\Events\OfferAccepted;
use Fundly\Shared\Bus\CommandBus;
use Fundly\Shared\Security\Principal;
use Fundly\Shared\Security\SystemIdentity;
use Fundly\Shared\Tenancy\TenantContext;
use Psr\Log\LoggerInterface;
use Throwable;

/** Accepted → ConditionsPrecedent: the approvers' conditions become the CP/CS register (TRD §6.2). */
final class ConditionsProgression
{
    public function __construct(private readonly CommandBus $bus, private readonly TenantContext $tenant, private readonly LoggerInterface $log) {}

    public function onOfferAccepted(OfferAccepted $e): void
    {
        $this->tenant->run($e->tenantId, function () use ($e): void {
            try {
                $this->bus->dispatch(new ImportConditions($e->applicationId), Principal::system($e->tenantId, SystemIdentity::Workflow));
            } catch (Throwable $ex) {
                $this->log->error('Opening conditions failed', ['application_id' => $e->applicationId, 'error' => $ex->getMessage()]);
            }
        });
    }
}

<?php

declare(strict_types=1);

namespace Fundly\Modules\Workflow\Application;

use Fundly\Modules\Application\Contracts\Events\ApplicationStatusChanged;
use Fundly\Modules\Workflow\Application\Commands\SyncWorkflowTask;
use Fundly\Shared\Bus\CommandBus;
use Fundly\Shared\Security\Principal;
use Fundly\Shared\Security\SystemIdentity;
use Fundly\Shared\Tenancy\TenantContext;
use Psr\Log\LoggerInterface;
use Throwable;

/** Every canonical status change re-syncs the application's stage task. A failure is logged, never undoes the business change. */
final class WorkflowProgression
{
    public function __construct(private readonly CommandBus $bus, private readonly TenantContext $tenant, private readonly LoggerInterface $log) {}

    public function onStatusChanged(ApplicationStatusChanged $e): void
    {
        $this->tenant->run($e->tenantId, function () use ($e): void {
            try {
                $this->bus->dispatch(new SyncWorkflowTask($e->applicationId, $e->from, $e->to, $e->actorId), Principal::system($e->tenantId, SystemIdentity::Workflow));
            } catch (Throwable $ex) {
                $this->log->error('Workflow task sync failed', ['application_id' => $e->applicationId, 'to' => $e->to, 'error' => $ex->getMessage()]);
            }
        });
    }
}

<?php

declare(strict_types=1);

namespace Fundly\Modules\Approval\Application;

use Fundly\Modules\Application\Contracts\CanonicalStatus;
use Fundly\Modules\Application\Contracts\Events\ApplicationStatusChanged;
use Fundly\Modules\Approval\Application\Commands\OpenApprovalRequest;
use Fundly\Shared\Bus\CommandBus;
use Fundly\Shared\Security\Principal;
use Fundly\Shared\Security\SystemIdentity;
use Fundly\Shared\Tenancy\TenantContext;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Recommended → Approval: routes the application through its approval matrix
 * as soon as it is recommended (TRD §6.2/§6.3). A routing failure (no matrix
 * bound, nothing matched) is logged and leaves the application Recommended,
 * so the configuration can be fixed without losing the recommendation.
 */
final class ApprovalProgression
{
    public function __construct(private readonly CommandBus $bus, private readonly TenantContext $tenant, private readonly LoggerInterface $log) {}

    public function onStatusChanged(ApplicationStatusChanged $e): void
    {
        if ($e->to !== CanonicalStatus::Recommended->value) {
            return;
        }
        $this->tenant->run($e->tenantId, function () use ($e): void {
            try {
                $this->bus->dispatch(new OpenApprovalRequest($e->applicationId, $e->actorId), Principal::system($e->tenantId, SystemIdentity::Workflow));
            } catch (Throwable $ex) {
                $this->log->error('Approval routing failed', ['application_id' => $e->applicationId, 'error' => $ex->getMessage()]);
            }
        });
    }
}

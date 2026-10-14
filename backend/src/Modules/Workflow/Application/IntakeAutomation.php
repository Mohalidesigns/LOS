<?php

declare(strict_types=1);

namespace Fundly\Modules\Workflow\Application;

use Fundly\Modules\Application\Contracts\ApplicationLifecycle;
use Fundly\Modules\Application\Contracts\CanonicalStatus;
use Fundly\Modules\Application\Contracts\Events\ApplicationStatusChanged;
use Fundly\Shared\Security\Principal;
use Fundly\Shared\Security\SystemIdentity;
use Fundly\Shared\Tenancy\TenantContext;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Automated intake stages (TRD §6.1/§6.2, FR-WFL-011), where the pinned
 * workflow marks the stage `automation: advance` (the built-in default does):
 *   Submitted → PreQualified: eligibility knock-outs. With no active rule set
 *     bound yet, nothing can knock out; P1 automation never auto-declines
 *     (D-038b), so the outcome is a pass.
 *   PreQualified → KycScreening: always (screening and CDD run there).
 */
final class IntakeAutomation
{
    public function __construct(
        private readonly ApplicationLifecycle $lifecycle,
        private readonly WorkflowFile $file,
        private readonly TenantContext $tenant,
        private readonly LoggerInterface $log,
    ) {}

    public function onStatusChanged(ApplicationStatusChanged $e): void
    {
        $next = match ($e->to) {
            CanonicalStatus::Submitted->value => [CanonicalStatus::PreQualified, 'ELIGIBILITY_PASS', 'No knock-out rule fired (rule set binding pending P1-CRD-01).'],
            CanonicalStatus::PreQualified->value => [CanonicalStatus::KycScreening, 'STAGE_AUTO_ADVANCE', null],
            default => null,
        };
        if ($next === null) {
            return;
        }
        $this->tenant->run($e->tenantId, function () use ($e, $next): void {
            try {
                // only stages the workflow marks as automated advance on their own (FR-WFL-011)
                $app = $this->file->application($e->applicationId);
                if (($this->file->peek($app)->stageFor($e->to)['automation'] ?? null) !== 'advance') {
                    return;
                }
                $this->lifecycle->advance($e->applicationId, $next[0], $next[1], $next[2], Principal::system($e->tenantId, SystemIdentity::Workflow));
            } catch (Throwable $ex) {
                $this->log->error('Intake automation failed', ['application_id' => $e->applicationId, 'to' => $next[0]->value, 'error' => $ex->getMessage()]);
            }
        });
    }
}

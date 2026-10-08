<?php

declare(strict_types=1);

namespace Fundly\Modules\Document\Application;

use Fundly\Modules\Application\Contracts\ApplicationLifecycle;
use Fundly\Modules\Application\Contracts\ApplicationReader;
use Fundly\Modules\Application\Contracts\CanonicalStatus;
use Fundly\Modules\Application\Contracts\Events\ApplicationCreated;
use Fundly\Modules\Application\Contracts\Events\ApplicationStatusChanged;
use Fundly\Modules\Document\Contracts\Events\ChecklistChanged;
use Fundly\Modules\Document\Domain\ChecklistSummary;
use Fundly\Modules\Document\Infrastructure\Models\ChecklistItem;
use Fundly\Shared\Security\Principal;
use Fundly\Shared\Security\SystemIdentity;
use Fundly\Shared\Tenancy\TenantContext;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Keeps the checklist in step with the application and moves it from
 * Documentation to Assessment when every mandatory item is verified or
 * waived (TRD §6.2). Listener failures are logged, never propagated (D-043).
 */
final class DocumentationProgression
{
    public function __construct(
        private readonly ChecklistSync $sync,
        private readonly ApplicationReader $applications,
        private readonly ApplicationLifecycle $lifecycle,
        private readonly TenantContext $tenant,
        private readonly LoggerInterface $log,
    ) {}

    public function onCreated(ApplicationCreated $e): void
    {
        $this->safely($e->tenantId, $e->applicationId, fn () => $this->sync->sync($e->applicationId));
    }

    public function onStatusChanged(ApplicationStatusChanged $e): void
    {
        if ($e->to === CanonicalStatus::Documentation->value) {
            $this->safely($e->tenantId, $e->applicationId, function () use ($e): void {
                $this->sync->sync($e->applicationId);
                $this->progress($e->tenantId, $e->applicationId);
            });
        }
    }

    public function onChecklistChanged(ChecklistChanged $e): void
    {
        $this->safely($e->tenantId, $e->applicationId, fn () => $this->progress($e->tenantId, $e->applicationId));
    }

    private function progress(string $tenantId, string $applicationId): void
    {
        $app = $this->applications->find($applicationId);
        if ($app === null || $app->status !== CanonicalStatus::Documentation) {
            return;
        }
        $items = [];
        foreach (ChecklistItem::query()->where('application_id', $applicationId)->get(['name', 'mandatory', 'status']) as $i) {
            $items[] = ['name' => $i->name, 'mandatory' => $i->mandatory, 'status' => $i->status];
        }
        $summary = ChecklistSummary::of($items);
        if ($items !== [] && $summary['complete']) {
            $this->lifecycle->advance($applicationId, CanonicalStatus::Assessment, 'CHECKLIST_COMPLETE', "{$summary['mandatory_satisfied']}/{$summary['mandatory_total']} mandatory items verified or waived", Principal::system($tenantId, SystemIdentity::Workflow));
        }
    }

    private function safely(string $tenantId, string $applicationId, callable $work): void
    {
        $this->tenant->run($tenantId, function () use ($work, $applicationId): void {
            try {
                $work();
            } catch (Throwable $ex) {
                $this->log->error('Documentation progression failed', ['application_id' => $applicationId, 'error' => $ex->getMessage()]);
            }
        });
    }
}

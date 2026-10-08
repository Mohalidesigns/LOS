<?php

declare(strict_types=1);

namespace Fundly\Modules\Document\Console;

use Fundly\Modules\Document\Contracts\Events\ChecklistChanged;
use Fundly\Modules\Document\Domain\ChecklistStatus;
use Fundly\Modules\Document\Infrastructure\Models\ChecklistItem;
use Fundly\Modules\Platform\Contracts\TenantDirectory;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Audit\AuditTrail;
use Fundly\Shared\Clock\Clock;
use Fundly\Shared\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;

/** Daily: verified documents past their validity become `expired` and must be refreshed (FR-DOC-009). */
final class ExpireDocumentsCommand extends Command
{
    protected $signature = 'documents:expire';

    protected $description = 'Mark verified checklist items whose validity has lapsed as expired.';

    public function handle(TenantDirectory $tenants, TenantContext $tenant, Clock $clock, AuditTrail $audit, Dispatcher $events): int
    {
        $today = $clock->now()->format('Y-m-d');
        $total = 0;
        foreach ($tenants->activeTenantIds() as $tenantId) {
            $tenant->run($tenantId, function () use ($today, $audit, $events, $tenantId, &$total): void {
                $due = ChecklistItem::query()->where('status', ChecklistStatus::Verified->value)->whereNotNull('valid_until')->where('valid_until', '<', $today)->get();
                foreach ($due as $item) {
                    DB::transaction(function () use ($item, $audit): void {
                        $item->forceFill(['status' => ChecklistStatus::Expired->value])->save();
                        $audit->record(new AuditEntry(action: 'document.checklist_item.expired', entityType: 'checklist_item', entityId: $item->id, before: ['status' => 'verified'], after: ['status' => 'expired', 'valid_until' => $item->valid_until?->toDateString()]));
                    });
                    $events->dispatch(new ChecklistChanged($tenantId, $item->application_id, $item->code, ChecklistStatus::Expired->value));
                    $total++;
                }
            });
        }
        $this->info("{$total} checklist item(s) expired.");

        return self::SUCCESS;
    }
}

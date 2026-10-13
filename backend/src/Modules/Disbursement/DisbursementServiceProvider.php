<?php

declare(strict_types=1);

namespace Fundly\Modules\Disbursement;

use Fundly\Integration\Runtime\Outbox\OutboxHandlerRegistry;
use Fundly\Modules\Access\Application\ChangeRequests\ChangeActionRegistry;
use Fundly\Modules\Application\Contracts\ApplicationReader;
use Fundly\Modules\Disbursement\Application\DisbursementFile;
use Fundly\Modules\Disbursement\Application\ReleaseDisbursementAction;
use Fundly\Modules\Disbursement\Console\ReconcileCommand;
use Fundly\Modules\Disbursement\Infrastructure\BookingSagaOutboxHandler;
use Fundly\Modules\Disbursement\Infrastructure\Models\Disbursement;
use Fundly\Modules\Disbursement\Infrastructure\Models\ReconciliationBreak;
use Fundly\Shared\Security\ResourceAttributes;
use Fundly\Shared\Security\ResourceResolver;
use Illuminate\Support\ServiceProvider;

/** M14 Disbursement: maker-checker release, booking saga, exception queue, daily reconciliation (FR-DSB-001..011). */
final class DisbursementServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(DisbursementFile::class);
    }

    public function boot(ResourceResolver $resources, OutboxHandlerRegistry $outbox): void
    {
        $this->app->make(ChangeActionRegistry::class)->register(ReleaseDisbursementAction::TYPE, ReleaseDisbursementAction::class);
        $outbox->register(ReleaseDisbursementAction::SAGA_TOPIC, BookingSagaOutboxHandler::class);
        $app = $this->app;
        $resources->register('disbursement', static function (string $id) use ($app): ?ResourceAttributes {
            $d = preg_match('/^[0-9a-f-]{36}$/', $id) === 1 ? Disbursement::query()->find($id) : null;
            $a = $d === null ? null : $app->make(ApplicationReader::class)->find($d->application_id);

            return $a === null || $d === null ? null : new ResourceAttributes(legalEntityId: $a->legalEntityId, orgUnitId: $a->orgUnitId, productId: $a->productId, segment: $a->segment, entityType: 'disbursement', entityId: $d->id);
        });
        $resources->register('reconciliation_break', static fn (string $id): ?ResourceAttributes => preg_match('/^[0-9a-f-]{36}$/', $id) === 1 && ReconciliationBreak::query()->whereKey($id)->exists() ? new ResourceAttributes(entityType: 'reconciliation_break', entityId: $id) : null);
        if ($this->app->runningInConsole()) {
            $this->commands([ReconcileCommand::class]);
        }
    }
}

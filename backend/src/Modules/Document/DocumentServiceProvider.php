<?php

declare(strict_types=1);

namespace Fundly\Modules\Document;

use Fundly\Modules\Access\Application\ChangeRequests\ChangeActionRegistry;
use Fundly\Modules\Application\Contracts\ApplicationReader;
use Fundly\Modules\Application\Contracts\Events\ApplicationCreated;
use Fundly\Modules\Application\Contracts\Events\ApplicationStatusChanged;
use Fundly\Modules\Document\Application\DocumentationProgression;
use Fundly\Modules\Document\Application\WaiveChecklistItemAction;
use Fundly\Modules\Document\Application\WaiveChecklistItemCreditAuthority;
use Fundly\Modules\Document\Application\WaiveChecklistItemStandard;
use Fundly\Modules\Document\Console\ExpireDocumentsCommand;
use Fundly\Modules\Document\Contracts\Events\ChecklistChanged;
use Fundly\Modules\Document\Infrastructure\Models\ChecklistItem;
use Fundly\Shared\Security\ResourceAttributes;
use Fundly\Shared\Security\ResourceResolver;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;

/** M07 Documents: upload with malware gate, encrypted immutable versions, checklist, waivers, expiry. */
final class DocumentServiceProvider extends ServiceProvider
{
    public function boot(ResourceResolver $resources, Dispatcher $events): void
    {
        $registry = $this->app->make(ChangeActionRegistry::class);
        $registry->register(WaiveChecklistItemAction::TYPE_STANDARD, WaiveChecklistItemStandard::class);
        $registry->register(WaiveChecklistItemAction::TYPE_CREDIT_AUTHORITY, WaiveChecklistItemCreditAuthority::class);

        // A checklist item is scoped like its application.
        $app = $this->app;
        $resources->register('checklist_item', static function (string $id) use ($app): ?ResourceAttributes {
            $item = preg_match('/^[0-9a-f-]{36}$/', $id) === 1 ? ChecklistItem::query()->find($id) : null;
            if ($item === null) {
                return null;
            }
            $application = $app->make(ApplicationReader::class)->find($item->application_id);

            return new ResourceAttributes(
                legalEntityId: $application?->legalEntityId,
                orgUnitId: $application?->orgUnitId,
                productId: $application?->productId,
                segment: $application?->segment,
                entityType: 'checklist_item',
                entityId: $item->id,
            );
        });

        $events->listen(ApplicationCreated::class, [DocumentationProgression::class, 'onCreated']);
        $events->listen(ApplicationStatusChanged::class, [DocumentationProgression::class, 'onStatusChanged']);
        $events->listen(ChecklistChanged::class, [DocumentationProgression::class, 'onChecklistChanged']);

        if ($this->app->runningInConsole()) {
            $this->commands([ExpireDocumentsCommand::class]);
        }
    }
}

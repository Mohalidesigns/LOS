<?php

declare(strict_types=1);

namespace Fundly\Modules\Document\Application;

use Fundly\Modules\Application\Contracts\ApplicationReader;
use Fundly\Modules\Document\Domain\ChecklistStatus;
use Fundly\Modules\Document\Infrastructure\Models\ChecklistItem;
use Fundly\Modules\Party\Contracts\PartyDirectory;
use Fundly\Modules\Product\Contracts\ProductCatalogue;
use Fundly\Shared\Money\Money;

/**
 * Derives the checklist from the application's *pinned* product version
 * (FR-DOC-007, FR-PRD-004/006). Idempotent: missing items are added; items
 * with a history are never removed.
 */
final class ChecklistSync
{
    public function __construct(
        private readonly ApplicationReader $applications,
        private readonly ProductCatalogue $products,
        private readonly PartyDirectory $parties,
    ) {}

    public function sync(string $applicationId): void
    {
        $app = $this->applications->find($applicationId);
        if ($app === null) {
            return;
        }
        $product = $this->products->version($app->productVersionId);
        if ($product === null) {
            return;
        }
        $types = array_values(array_unique(array_map(static fn ($p): string => $p->type, $this->parties->findMany(array_keys($app->applicants)))));
        $existing = ChecklistItem::query()->where('application_id', $applicationId)->pluck('code')->all();
        foreach ($product->checklistFor($types, $app->channel, $app->requestedAmount ?? Money::zero($product->currency)) as $position => $item) {
            if (in_array($item['code'], $existing, true)) {
                continue;
            }
            $row = new ChecklistItem;
            $row->forceFill([
                'application_id' => $applicationId,
                'code' => $item['code'],
                'name' => $item['name'],
                'position' => $position,
                'mandatory' => $item['mandatory'],
                'status' => ChecklistStatus::NotReceived->value,
                'waiver_authority' => $item['waiver_authority'],
                'validity_days' => $item['validity_days'],
            ])->save();
        }
    }
}

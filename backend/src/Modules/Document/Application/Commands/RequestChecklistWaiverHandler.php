<?php

declare(strict_types=1);

namespace Fundly\Modules\Document\Application\Commands;

use Fundly\Modules\Access\Contracts\ChangeRequestGateway;
use Fundly\Modules\Document\Application\WaiveChecklistItemAction;
use Fundly\Modules\Document\Domain\ChecklistStatus;
use Fundly\Modules\Document\Infrastructure\Models\ChecklistItem;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Exceptions\NotFound;

final class RequestChecklistWaiverHandler implements CommandHandler
{
    public function __construct(private readonly ChangeRequestGateway $changeRequests) {}

    /** @return array<string, mixed> the change request awaiting a checker */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof RequestChecklistWaiver);
        $item = ChecklistItem::query()->lockForUpdate()->find($command->itemId) ?? throw new NotFound('Checklist item not found.');
        ChecklistStatus::from($item->status)->assertCanWaive();
        $cr = $this->changeRequests->submit(WaiveChecklistItemAction::typeFor($item->waiver_authority), ['checklist_item_id' => $item->id, 'reason' => (string) $command->reason], $command->reason, $context);
        $item->forceFill(['waiver_change_request_id' => is_string($cr['id'] ?? null) ? $cr['id'] : null, 'waiver_reason' => $command->reason])->save();

        return $cr;
    }
}

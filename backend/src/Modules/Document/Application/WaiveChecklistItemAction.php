<?php

declare(strict_types=1);

namespace Fundly\Modules\Document\Application;

use Fundly\Modules\Access\Contracts\ChangeAction;
use Fundly\Modules\Document\Contracts\Events\ChecklistChanged;
use Fundly\Modules\Document\Domain\ChecklistStatus;
use Fundly\Modules\Document\Infrastructure\Models\ChecklistItem;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\Payload;
use Fundly\Shared\Exceptions\NotFound;
use Fundly\Shared\Security\Principal;
use Fundly\Shared\Security\ResourceRef;

/**
 * Waiver of a checklist item, applied only when the configured authority
 * approves (FR-DOC-008, TRD §6.4 "waiver approval"). One action type per
 * authority, because a change action declares a fixed checker permission.
 */
abstract class WaiveChecklistItemAction implements ChangeAction
{
    public const TYPE_STANDARD = 'document.checklist.waive';

    public const TYPE_CREDIT_AUTHORITY = 'document.checklist.waive_credit_authority';

    public static function typeFor(string $authority): string
    {
        return $authority === 'application:approve' ? self::TYPE_CREDIT_AUTHORITY : self::TYPE_STANDARD;
    }

    public function makerPermission(): string
    {
        return 'document:verify';
    }

    public function entity(array $payload): ResourceRef
    {
        return new ResourceRef('checklist_item', Payload::string($payload, 'checklist_item_id'));
    }

    public function validate(array $payload, Principal $maker): void
    {
        ChecklistStatus::from($this->item($payload)->status)->assertCanWaive();
    }

    public function fingerprint(array $payload): string
    {
        $item = $this->item($payload);

        return hash('sha256', $item->id.'|'.$item->status.'|'.($item->document_id ?? ''));
    }

    public function execute(array $payload, string $changeRequestId, CommandContext $context): array
    {
        $item = $this->item($payload);
        $before = ['status' => $item->status];
        $item->forceFill(['status' => ChecklistStatus::Waived->value, 'waiver_reason' => Payload::string($payload, 'reason'), 'waiver_change_request_id' => $changeRequestId])->save();
        $context->audit(new AuditEntry(action: 'document.checklist_item.waived', entityType: 'checklist_item', entityId: $item->id, before: $before, after: [
            'status' => $item->status, 'code' => $item->code, 'authority' => $item->waiver_authority, 'change_request_id' => $changeRequestId,
        ], reasonText: Payload::string($payload, 'reason')));
        $context->raise(new ChecklistChanged($context->principal->tenantId, $item->application_id, $item->code, $item->status));

        return ['checklist_item_id' => $item->id, 'status' => $item->status];
    }

    public function excludedCheckers(array $payload): array
    {
        return [];
    }

    /** @param array<string, mixed> $payload */
    private function item(array $payload): ChecklistItem
    {
        return ChecklistItem::query()->find(Payload::optionalString($payload, 'checklist_item_id')) ?? throw new NotFound('Checklist item not found.');
    }
}

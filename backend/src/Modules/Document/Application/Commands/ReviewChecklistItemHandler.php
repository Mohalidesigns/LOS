<?php

declare(strict_types=1);

namespace Fundly\Modules\Document\Application\Commands;

use Fundly\Modules\Document\Application\DocumentPresenter;
use Fundly\Modules\Document\Contracts\Events\ChecklistChanged;
use Fundly\Modules\Document\Domain\ChecklistStatus;
use Fundly\Modules\Document\Infrastructure\Models\ChecklistItem;
use Fundly\Modules\Document\Infrastructure\Models\DocumentVersion;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Clock\Clock;
use Fundly\Shared\Exceptions\DomainRuleViolation;
use Fundly\Shared\Exceptions\NotFound;
use Illuminate\Support\Carbon;

final class ReviewChecklistItemHandler implements CommandHandler
{
    public function __construct(private readonly Clock $clock) {}

    /** @return array{data: array<string, mixed>} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof ReviewChecklistItem);
        $item = ChecklistItem::query()->lockForUpdate()->find($command->itemId) ?? throw new NotFound('Checklist item not found.');
        ChecklistStatus::from($item->status)->assertCanReview();
        $latest = DocumentVersion::query()->where('document_id', $item->document_id)->where('scan_status', 'clean')->orderByDesc('version_no')->first();
        // Segregation of duties: nobody verifies a document they uploaded themselves.
        if ($command->verb === 'verify' && $latest !== null && $latest->uploaded_by === $context->principal->id) {
            throw new DomainRuleViolation('You uploaded this document; a different officer must verify it.');
        }
        $before = ['status' => $item->status];
        $now = $this->clock->now();
        if ($command->verb === 'verify') {
            $validUntil = $command->validUntil ?? ($item->validity_days === null ? null : $now->modify("+{$item->validity_days} days")->format('Y-m-d'));
            $item->forceFill(['status' => ChecklistStatus::Verified->value, 'verified_by' => $context->principal->id, 'verified_at' => Carbon::instance($now), 'valid_until' => $validUntil, 'rejection_reason' => null])->save();
        } else {
            $item->forceFill(['status' => ChecklistStatus::Rejected->value, 'rejection_reason' => $command->reason, 'verified_by' => null, 'verified_at' => null])->save();
        }
        $context->audit(new AuditEntry(
            action: $command->action(),
            entityType: 'checklist_item',
            entityId: $item->id,
            before: $before,
            after: ['status' => $item->status, 'code' => $item->code, 'document_version_id' => $latest?->id, 'sha256' => $latest?->sha256, 'valid_until' => $item->valid_until?->toDateString(), 'note' => $command->note],
            reasonText: $command->reason,
        ));
        $context->raise(new ChecklistChanged($context->principal->tenantId, $item->application_id, $item->code, $item->status));

        return ['data' => DocumentPresenter::item($item->refresh(), $latest?->id)];
    }
}

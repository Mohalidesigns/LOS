<?php

declare(strict_types=1);

namespace Fundly\Modules\Document\Application\Commands;

use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Bus\ValidatesInput;
use Fundly\Shared\Security\ResourceRef;

/** Manual verification (the MVP "IDP" adapter) or rejection of a received document (FR-DOC-007/009). */
#[HandledBy(ReviewChecklistItemHandler::class)]
final readonly class ReviewChecklistItem implements Command, ValidatesInput
{
    public function __construct(
        public string $itemId,
        public string $verb,
        public ?string $reason,
        public ?string $note,
        public ?string $validUntil,
    ) {}

    public function action(): string
    {
        return $this->verb === 'reject' ? 'document.checklist_item.rejected' : 'document.checklist_item.verified';
    }

    public function permission(): string
    {
        return 'document:verify';
    }

    public function resource(): ResourceRef
    {
        return new ResourceRef('checklist_item', $this->itemId);
    }

    public function data(): array
    {
        return ['verb' => $this->verb, 'reason' => $this->reason, 'note' => $this->note, 'valid_until' => $this->validUntil];
    }

    public function rules(): array
    {
        return [
            'verb' => ['required', 'in:verify,reject'],
            'reason' => ['required_if:verb,reject', 'nullable', 'string', 'min:5', 'max:2000'],
            'note' => ['nullable', 'string', 'max:2000'],
            'valid_until' => ['nullable', 'date_format:Y-m-d', 'after:today'],
        ];
    }
}

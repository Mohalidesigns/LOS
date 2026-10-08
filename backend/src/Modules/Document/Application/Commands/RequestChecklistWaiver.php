<?php

declare(strict_types=1);

namespace Fundly\Modules\Document\Application\Commands;

use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Bus\ValidatesInput;
use Fundly\Shared\Security\ResourceRef;

/** Ask the configured authority to waive a checklist item (FR-DOC-008); applied only by a checker. */
#[HandledBy(RequestChecklistWaiverHandler::class)]
final readonly class RequestChecklistWaiver implements Command, ValidatesInput
{
    public function __construct(public string $itemId, public ?string $reason) {}

    public function action(): string
    {
        return 'document.checklist_item.waiver_requested';
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
        return ['reason' => $this->reason];
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:10', 'max:2000']];
    }
}

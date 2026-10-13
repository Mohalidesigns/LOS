<?php

declare(strict_types=1);

namespace Fundly\Modules\Handover\Application\Commands;

use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Bus\ValidatesInput;
use Fundly\Shared\Security\ResourceRef;

/** Servicing confirms it has taken over the facility and the items handed to it (FR-HND-003). */
#[HandledBy(AcknowledgeHandoverHandler::class)]
final readonly class AcknowledgeHandover implements Command, ValidatesInput
{
    public function __construct(public string $packageId, public ?string $note) {}

    public function action(): string
    {
        return 'handover.acknowledged';
    }

    public function permission(): string
    {
        return 'handover:acknowledge';
    }

    public function resource(): ResourceRef
    {
        return new ResourceRef('handover_package', $this->packageId);
    }

    public function data(): array
    {
        return ['note' => $this->note];
    }

    public function rules(): array
    {
        return ['note' => ['required', 'string', 'min:5', 'max:2000']];
    }
}

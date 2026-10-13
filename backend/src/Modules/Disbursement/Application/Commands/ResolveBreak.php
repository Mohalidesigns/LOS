<?php

declare(strict_types=1);

namespace Fundly\Modules\Disbursement\Application\Commands;

use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Bus\ValidatesInput;
use Fundly\Shared\Security\ResourceRef;

/** Close a reconciliation break with the investigation outcome. */
#[HandledBy(ResolveBreakHandler::class)]
final readonly class ResolveBreak implements Command, ValidatesInput
{
    public function __construct(public string $breakId, public ?string $note) {}

    public function action(): string
    {
        return 'reconciliation.break_resolved';
    }

    public function permission(): string
    {
        return 'reconciliation:run';
    }

    public function resource(): ResourceRef
    {
        return new ResourceRef('reconciliation_break', $this->breakId);
    }

    public function data(): array
    {
        return ['note' => $this->note];
    }

    public function rules(): array
    {
        return ['note' => ['required', 'string', 'min:10', 'max:2000']];
    }
}

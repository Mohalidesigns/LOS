<?php

declare(strict_types=1);

namespace Fundly\Modules\Workflow\Application\Commands;

use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Bus\ValidatesInput;
use Fundly\Shared\Security\ResourceRef;

#[HandledBy(SaveTaskViewHandler::class)]
final readonly class SaveTaskView implements Command, ValidatesInput
{
    public function __construct(public ?string $name, /** @var array<string, string> */ public array $filters) {}

    public function action(): string
    {
        return 'workflow.view_saved';
    }

    public function permission(): string
    {
        return 'application:view';
    }

    public function resource(): ?ResourceRef
    {
        return null;
    }

    public function data(): array
    {
        return ['name' => $this->name, 'filters' => $this->filters];
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'min:1', 'max:60'], 'filters' => ['array'], 'filters.view' => ['nullable', 'in:mine,queue,team'], 'filters.stage' => ['nullable', 'string', 'max:48'], 'filters.sla' => ['nullable', 'in:on_track,at_risk,breached,paused']];
    }
}

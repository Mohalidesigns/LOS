<?php

declare(strict_types=1);

namespace Fundly\Modules\Workflow\Application\Commands;

use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Security\ResourceRef;

/** System: move open tasks to at_risk / breached and escalate (FR-WFL-006). Run every minute. */
#[HandledBy(EvaluateSlaClocksHandler::class)]
final readonly class EvaluateSlaClocks implements Command
{
    public function __construct() {}

    public function action(): string
    {
        return 'workflow.sla_evaluated';
    }

    public function permission(): ?string
    {
        return null;
    }

    public function resource(): ?ResourceRef
    {
        return null;
    }

    /** @return array<string, mixed> */
    public function data(): array
    {
        return [];
    }
}

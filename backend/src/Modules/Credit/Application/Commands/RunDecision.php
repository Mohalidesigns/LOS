<?php

declare(strict_types=1);

namespace Fundly\Modules\Credit\Application\Commands;

use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Security\ResourceRef;

/** Run the bound rule set and record an immutable decision snapshot (FR-CRD-010/014, LOS-CON-006). */
#[HandledBy(RunDecisionHandler::class)]
final readonly class RunDecision implements Command
{
    public function __construct(public string $applicationId) {}

    public function action(): string
    {
        return 'credit.decision.recorded';
    }

    public function permission(): string
    {
        return 'credit:analyse';
    }

    public function resource(): ResourceRef
    {
        return new ResourceRef('application', $this->applicationId);
    }
}

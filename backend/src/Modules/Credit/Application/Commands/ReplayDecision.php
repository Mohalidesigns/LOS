<?php

declare(strict_types=1);

namespace Fundly\Modules\Credit\Application\Commands;

use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Security\ResourceRef;

/** Re-run a stored snapshot with its recorded rule-set and evaluator versions (FR-CRD-014). Audited, changes nothing. */
#[HandledBy(ReplayDecisionHandler::class)]
final readonly class ReplayDecision implements Command
{
    public function __construct(public string $decisionId) {}

    public function action(): string
    {
        return 'credit.decision.replayed';
    }

    public function permission(): string
    {
        return 'application:view';
    }

    public function resource(): ResourceRef
    {
        return new ResourceRef('decision', $this->decisionId);
    }
}

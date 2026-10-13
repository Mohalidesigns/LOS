<?php

declare(strict_types=1);

namespace Fundly\Modules\Conditions\Application\Commands;

use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Security\ResourceRef;

/** Open the CP/CS register when an offer is accepted: the approvers' conditions become tracked items (FR-APV-009 → FR-CPR-001). Platform-only. */
#[HandledBy(ImportConditionsHandler::class)]
final readonly class ImportConditions implements Command
{
    public function __construct(public string $applicationId) {}

    public function action(): string
    {
        return 'conditions.imported';
    }

    public function permission(): ?string
    {
        return null;
    }

    public function resource(): ResourceRef
    {
        return new ResourceRef('application', $this->applicationId);
    }
}

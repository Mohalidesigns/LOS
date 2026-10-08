<?php

declare(strict_types=1);

namespace Fundly\Modules\Compliance\Application\Commands;

use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Security\ResourceRef;

/**
 * Screen every applicant and related individual of an application
 * (FR-CUS-005, FR-CMP-011). Issued by the platform on entry to KycScreening
 * (system principal, via the outbox) or manually by a compliance officer.
 */
#[HandledBy(ScreenApplicationHandler::class)]
final readonly class ScreenApplication implements Command
{
    public function __construct(public string $applicationId, public string $trigger, public bool $manual = false) {}

    public function action(): string
    {
        return 'compliance.application.screened';
    }

    public function permission(): ?string
    {
        return $this->manual ? 'screening:review' : null;
    }

    public function resource(): ResourceRef
    {
        return new ResourceRef('application', $this->applicationId);
    }
}

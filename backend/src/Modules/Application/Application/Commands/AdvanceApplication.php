<?php

declare(strict_types=1);

namespace Fundly\Modules\Application\Application\Commands;

use Fundly\Modules\Application\Contracts\CanonicalStatus;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Security\ResourceRef;

/**
 * A canonical move requested by another module through ApplicationLifecycle.
 * The calling module has already authorised its own action; this command is
 * platform-issued (system principal) or carries the permission of the move.
 */
#[HandledBy(AdvanceApplicationHandler::class)]
final readonly class AdvanceApplication implements Command
{
    public function __construct(
        public string $applicationId,
        public CanonicalStatus $to,
        public string $reasonCode,
        public ?string $note,
        public ?string $permissionOverride = null,
    ) {}

    public function action(): string
    {
        return 'application.advanced';
    }

    public function permission(): ?string
    {
        return $this->permissionOverride;
    }

    public function resource(): ResourceRef
    {
        return new ResourceRef('application', $this->applicationId);
    }
}

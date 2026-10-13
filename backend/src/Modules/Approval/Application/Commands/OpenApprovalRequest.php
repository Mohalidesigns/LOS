<?php

declare(strict_types=1);

namespace Fundly\Modules\Approval\Application\Commands;

use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Security\ResourceRef;

/** Route a recommended application through the approval matrix (FR-APV-001/007). Platform-only. */
#[HandledBy(OpenApprovalRequestHandler::class)]
final readonly class OpenApprovalRequest implements Command
{
    public function __construct(public string $applicationId, public string $recommendedBy) {}

    public function action(): string
    {
        return 'approval.request.opened';
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

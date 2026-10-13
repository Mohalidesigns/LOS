<?php

declare(strict_types=1);

namespace Fundly\Modules\Approval\Application\Commands;

use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Security\ResourceRef;

/** An approval whose validity lapsed before an offer was issued (FR-APV-012). Platform-only. */
#[HandledBy(ExpireApprovalHandler::class)]
final readonly class ExpireApproval implements Command
{
    public function __construct(public string $requestId) {}

    public function action(): string
    {
        return 'approval.request.expired';
    }

    public function permission(): ?string
    {
        return null;
    }

    public function resource(): ResourceRef
    {
        return new ResourceRef('approval_request', $this->requestId);
    }
}

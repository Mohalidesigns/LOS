<?php

declare(strict_types=1);

namespace Fundly\Modules\Handover\Application\Commands;

use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Security\ResourceRef;

/** Hand a booked facility over to servicing and announce it (FR-HND-001/003/004). Platform-only. */
#[HandledBy(CreateHandoverPackageHandler::class)]
final readonly class CreateHandoverPackage implements Command
{
    public function __construct(public string $applicationId) {}

    public function action(): string
    {
        return 'handover.package_created';
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

<?php

declare(strict_types=1);

namespace Fundly\Modules\Disbursement\Application\Commands;

use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Security\ResourceRef;

/** Outbox step: drive the booking saga for a released instruction. Platform-only. */
#[HandledBy(RunBookingSagaHandler::class)]
final readonly class RunBookingSaga implements Command
{
    public function __construct(public string $disbursementId) {}

    public function action(): string
    {
        return 'disbursement.saga_run';
    }

    public function permission(): ?string
    {
        return null;
    }

    public function resource(): ResourceRef
    {
        return new ResourceRef('disbursement', $this->disbursementId);
    }
}

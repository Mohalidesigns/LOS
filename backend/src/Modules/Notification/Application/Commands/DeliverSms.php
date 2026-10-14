<?php

declare(strict_types=1);

namespace Fundly\Modules\Notification\Application\Commands;

use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Security\ResourceRef;

/** Outbox step: send one queued text from the communication log. Platform-only. */
#[HandledBy(DeliverSmsHandler::class)]
final readonly class DeliverSms implements Command
{
    public function __construct(public string $communicationId) {}

    public function action(): string
    {
        return 'notification.sms_sent';
    }

    public function permission(): ?string
    {
        return null;
    }

    public function resource(): ResourceRef
    {
        return new ResourceRef('communication', $this->communicationId);
    }
}

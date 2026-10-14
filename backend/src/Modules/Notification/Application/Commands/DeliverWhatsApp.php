<?php

declare(strict_types=1);

namespace Fundly\Modules\Notification\Application\Commands;

use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Security\ResourceRef;

/** Outbox step: send one queued WhatsApp template message from the communication log. Platform-only. */
#[HandledBy(DeliverWhatsAppHandler::class)]
final readonly class DeliverWhatsApp implements Command
{
    public function __construct(public string $communicationId) {}

    public function action(): string
    {
        return 'notification.whatsapp_sent';
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

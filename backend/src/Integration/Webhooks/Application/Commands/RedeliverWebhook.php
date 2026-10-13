<?php

declare(strict_types=1);

namespace Fundly\Integration\Webhooks\Application\Commands;

use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Security\ResourceRef;

/** Send a delivery again (after the receiver fixed its side). */
#[HandledBy(RedeliverWebhookHandler::class)]
final readonly class RedeliverWebhook implements Command
{
    public function __construct(public string $deliveryId) {}

    public function action(): string
    {
        return 'webhook.redelivered';
    }

    public function permission(): string
    {
        return 'webhook:manage';
    }

    public function resource(): ResourceRef
    {
        return new ResourceRef('webhook_delivery', $this->deliveryId);
    }
}

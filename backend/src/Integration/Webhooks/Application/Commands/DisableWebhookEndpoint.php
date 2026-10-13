<?php

declare(strict_types=1);

namespace Fundly\Integration\Webhooks\Application\Commands;

use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Security\ResourceRef;

/** Stop sending to an endpoint; pending deliveries are skipped. */
#[HandledBy(DisableWebhookEndpointHandler::class)]
final readonly class DisableWebhookEndpoint implements Command
{
    public function __construct(public string $endpointId) {}

    public function action(): string
    {
        return 'webhook.endpoint_disabled';
    }

    public function permission(): string
    {
        return 'webhook:manage';
    }

    public function resource(): ResourceRef
    {
        return new ResourceRef('webhook_endpoint', $this->endpointId);
    }
}

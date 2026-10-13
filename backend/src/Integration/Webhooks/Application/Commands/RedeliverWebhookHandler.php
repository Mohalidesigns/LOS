<?php

declare(strict_types=1);

namespace Fundly\Integration\Webhooks\Application\Commands;

use Fundly\Integration\Webhooks\Application\WebhookQueries;
use Fundly\Integration\Webhooks\Models\WebhookDelivery;
use Fundly\Integration\Webhooks\Models\WebhookEndpoint;
use Fundly\Integration\Webhooks\WebhookPublisher;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Exceptions\DomainRuleViolation;
use Fundly\Shared\Exceptions\NotFound;

/** The receiver de-duplicates on Fundly-Event-Id; a redelivery carries the same event id. */
final class RedeliverWebhookHandler implements CommandHandler
{
    public function __construct(private readonly WebhookQueries $queries) {}

    /** @return array{data: array<string, mixed>} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof RedeliverWebhook);
        $d = WebhookDelivery::query()->find($command->deliveryId) ?? throw new NotFound('Delivery not found.');
        if (WebhookEndpoint::query()->whereKey($d->endpoint_id)->value('status') !== 'active') {
            throw new DomainRuleViolation('The endpoint is disabled.');
        }
        $d->forceFill(['status' => 'pending'])->save();
        $context->outbox(WebhookPublisher::intent($context->principal->tenantId, $d->id, 'redeliver-'.($d->attempts + 1)));
        $context->audit(new AuditEntry(action: $command->action(), entityType: 'webhook_delivery', entityId: $d->id, after: ['attempts_so_far' => $d->attempts]));

        return ['data' => $this->queries->delivery($d)];
    }
}

<?php

declare(strict_types=1);

namespace Fundly\Integration\Webhooks;

use Fundly\Integration\Webhooks\Models\WebhookDelivery;
use Fundly\Integration\Webhooks\Models\WebhookEndpoint;
use Fundly\Integration\Webhooks\Models\WebhookEvent;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\OutboxIntent;
use Fundly\Shared\Clock\Clock;

/**
 * Publish a business event to every active endpoint subscribed to it. Runs
 * inside the caller's command: the event, its deliveries and their outbox
 * messages commit with the business change, so nothing is announced that
 * did not happen and nothing that happened goes unannounced.
 */
final class WebhookPublisher
{
    public const TOPIC = 'webhook.deliver';

    public function __construct(private readonly Clock $clock) {}

    /**
     * @param  array<string, mixed>  $data
     * @return string the event id
     */
    public function publish(string $type, string $subjectType, string $subjectId, array $data, CommandContext $context): string
    {
        $event = new WebhookEvent;
        $event->forceFill(['type' => $type, 'subject_type' => $subjectType, 'subject_id' => $subjectId, 'payload' => $data, 'occurred_at' => $this->clock->now()])->save();
        foreach (WebhookEndpoint::query()->where('status', 'active')->orderBy('created_at')->get() as $endpoint) {
            if (! in_array($type, $endpoint->events, true) && ! in_array('*', $endpoint->events, true)) {
                continue;
            }
            $delivery = new WebhookDelivery;
            $delivery->forceFill(['event_id' => $event->id, 'endpoint_id' => $endpoint->id, 'status' => 'pending', 'attempts' => 0, 'created_at' => $this->clock->now()])->save();
            $context->outbox(self::intent($context->principal->tenantId, $delivery->id, 'first'));
        }

        return $event->id;
    }

    public static function intent(string $tenantId, string $deliveryId, string $round): OutboxIntent
    {
        return new OutboxIntent(
            topic: self::TOPIC,
            payload: ['delivery_id' => $deliveryId],
            aggregateType: 'webhook_delivery',
            aggregateId: $deliveryId,
            idempotencyKey: "{$tenantId}:webhook:{$deliveryId}:{$round}",
            maxAttempts: 10,
        );
    }

    /**
     * The JSON body sent for an event (stable field order, so the signature is reproducible).
     *
     * @param  array<string, mixed>  $data
     */
    public static function body(WebhookEvent $event, array $data): string
    {
        return (string) json_encode([
            'id' => $event->id,
            'type' => $event->type,
            'occurred_at' => $event->occurred_at->toIso8601String(),
            'tenant_id' => $event->tenant_id,
            'data' => $data,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }
}

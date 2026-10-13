<?php

declare(strict_types=1);

namespace Fundly\Integration\Webhooks;

use Fundly\Integration\Runtime\Errors\RetryableError;
use Fundly\Integration\Runtime\Outbox\OutboxHandler;
use Fundly\Integration\Runtime\Outbox\OutboxMessage;
use Fundly\Integration\Webhooks\Models\WebhookDelivery;
use Fundly\Integration\Webhooks\Models\WebhookEndpoint;
use Fundly\Integration\Webhooks\Models\WebhookEvent;
use Fundly\Shared\Clock\Clock;
use Fundly\Shared\Crypto\FieldEncryptor;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Delivers one event to one endpoint. A 2xx is delivered; anything else is
 * recorded and handed back to the outbox to retry with backoff. Each attempt
 * is logged on the delivery (TRD §10.1 "retries and backoff, delivery log").
 */
final class WebhookDeliveryHandler implements OutboxHandler
{
    public function __construct(private readonly FieldEncryptor $crypto, private readonly Clock $clock) {}

    public function topic(): string
    {
        return WebhookPublisher::TOPIC;
    }

    public function handle(OutboxMessage $message): array
    {
        $delivery = WebhookDelivery::query()->find((string) $message->payload['delivery_id']);
        if ($delivery === null || $delivery->status === 'delivered') {
            return ['status' => $delivery->status ?? 'missing'];
        }
        $endpoint = WebhookEndpoint::query()->findOrFail($delivery->endpoint_id);
        $event = WebhookEvent::query()->findOrFail($delivery->event_id);
        $now = $this->clock->now();
        if ($endpoint->status !== 'active') {
            $delivery->forceFill(['status' => 'skipped', 'last_error' => 'Endpoint disabled.', 'last_attempt_at' => $now])->save();

            return ['status' => 'skipped'];
        }
        $body = WebhookPublisher::body($event, $event->payload);
        $signature = WebhookSigner::header($this->crypto->decrypt($endpoint->secret_enc, 'webhook.secret'), $now->getTimestamp(), $body);
        $attempts = $delivery->attempts + 1;
        try {
            $response = Http::timeout(10)->withBody($body, 'application/json')->withHeaders([
                'Fundly-Signature' => $signature,
                'Fundly-Event-Id' => $event->id,
                'Fundly-Event-Type' => $event->type,
                'Fundly-Delivery-Id' => $delivery->id,
                'User-Agent' => 'Fundly-LOS-Webhooks/1',
            ])->post($endpoint->url);
        } catch (ConnectionException $e) {
            $delivery->forceFill(['status' => 'failing', 'attempts' => $attempts, 'last_http_status' => null, 'last_error' => mb_substr($e->getMessage(), 0, 500), 'last_attempt_at' => $now])->save();
            throw new RetryableError('WEBHOOK.TRANSPORT.UNREACHABLE', "Endpoint {$endpoint->name} is unreachable.");
        }
        if ($response->successful()) {
            $delivery->forceFill(['status' => 'delivered', 'attempts' => $attempts, 'last_http_status' => $response->status(), 'last_error' => null, 'last_attempt_at' => $now, 'delivered_at' => $now])->save();

            return ['status' => 'delivered', 'http_status' => $response->status()];
        }
        $delivery->forceFill(['status' => 'failing', 'attempts' => $attempts, 'last_http_status' => $response->status(), 'last_error' => mb_substr($response->body(), 0, 500), 'last_attempt_at' => $now])->save();
        throw new RetryableError('WEBHOOK.PROVIDER.REJECTED', "Endpoint {$endpoint->name} answered HTTP {$response->status()}.");
    }
}

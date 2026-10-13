<?php

declare(strict_types=1);

namespace Fundly\Integration\Webhooks\Application;

use Fundly\Integration\Webhooks\Models\WebhookDelivery;
use Fundly\Integration\Webhooks\Models\WebhookEndpoint;
use Fundly\Integration\Webhooks\Models\WebhookEvent;

final class WebhookQueries
{
    /** @return array{data: list<array<string, mixed>>} */
    public function endpoints(): array
    {
        return ['data' => array_values(WebhookEndpoint::query()->orderBy('created_at')->get()->map(fn (WebhookEndpoint $e): array => $this->endpoint($e))->all())];
    }

    /**
     * Recent deliveries, newest first; optionally for one event subject (e.g. an application).
     *
     * @return array{data: list<array<string, mixed>>}
     */
    public function deliveries(?string $subjectType = null, ?string $subjectId = null, int $limit = 50): array
    {
        $q = WebhookDelivery::query()->orderByDesc('created_at')->limit($limit);
        if ($subjectType !== null && $subjectId !== null) {
            $q->whereIn('event_id', WebhookEvent::query()->where('subject_type', $subjectType)->where('subject_id', $subjectId)->select('id'));
        }

        return ['data' => array_values($q->get()->map(fn (WebhookDelivery $d): array => $this->delivery($d))->all())];
    }

    /** @return array<string, mixed> */
    public function endpoint(WebhookEndpoint $e): array
    {
        return ['id' => $e->id, 'name' => $e->name, 'url' => $e->url, 'events' => $e->events, 'status' => $e->status, 'created_by' => $e->created_by, 'created_at' => $e->created_at->toIso8601String()];
    }

    /** @return array<string, mixed> */
    public function delivery(WebhookDelivery $d): array
    {
        $event = WebhookEvent::query()->find($d->event_id);
        $endpoint = WebhookEndpoint::query()->find($d->endpoint_id);

        return [
            'id' => $d->id, 'event_id' => $d->event_id, 'event_type' => $event?->type, 'subject' => $event === null ? null : ['type' => $event->subject_type, 'id' => $event->subject_id],
            'endpoint' => ['id' => $d->endpoint_id, 'name' => $endpoint?->name], 'status' => $d->status, 'attempts' => $d->attempts,
            'last_http_status' => $d->last_http_status, 'last_error' => $d->last_error,
            'last_attempt_at' => $d->last_attempt_at?->toIso8601String(), 'delivered_at' => $d->delivered_at?->toIso8601String(), 'created_at' => $d->created_at->toIso8601String(),
        ];
    }
}

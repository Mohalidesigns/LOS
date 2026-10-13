<?php

declare(strict_types=1);

namespace Fundly\Integration\Webhooks\Http;

use Fundly\Integration\Webhooks\Application\Commands\DisableWebhookEndpoint;
use Fundly\Integration\Webhooks\Application\Commands\RedeliverWebhook;
use Fundly\Integration\Webhooks\Application\Commands\RegisterWebhookEndpoint;
use Fundly\Integration\Webhooks\Application\WebhookQueries;
use Fundly\Shared\Bus\CommandBus;
use Fundly\Shared\Http\ApiResponse;
use Fundly\Shared\Security\CurrentPrincipal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class WebhookController
{
    public function __construct(private readonly CommandBus $bus, private readonly CurrentPrincipal $principal, private readonly WebhookQueries $queries) {}

    public function endpoints(): JsonResponse
    {
        return ApiResponse::json($this->queries->endpoints());
    }

    public function register(Request $request): JsonResponse
    {
        $events = $request->input('events', []);
        $name = $request->input('name');
        $url = $request->input('url');

        return ApiResponse::resource($this->bus->dispatch(new RegisterWebhookEndpoint(is_string($name) ? trim($name) : null, is_string($url) ? trim($url) : null, is_array($events) ? array_values($events) : []), $this->principal->require()), 201);
    }

    public function disable(string $id): JsonResponse
    {
        return ApiResponse::resource($this->bus->dispatch(new DisableWebhookEndpoint($id), $this->principal->require()));
    }

    public function deliveries(Request $request): JsonResponse
    {
        $type = $request->query('filter')['subject_type'] ?? null;
        $id = $request->query('filter')['subject_id'] ?? null;

        return ApiResponse::json($this->queries->deliveries(is_string($type) ? $type : null, is_string($id) ? $id : null));
    }

    public function redeliver(string $id): JsonResponse
    {
        return ApiResponse::resource($this->bus->dispatch(new RedeliverWebhook($id), $this->principal->require()));
    }
}

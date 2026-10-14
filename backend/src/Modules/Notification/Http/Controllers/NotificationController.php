<?php

declare(strict_types=1);

namespace Fundly\Modules\Notification\Http\Controllers;

use Fundly\Modules\Notification\Application\Commands\MarkAllNotificationsRead;
use Fundly\Modules\Notification\Application\Commands\MarkNotificationRead;
use Fundly\Modules\Notification\Application\NotificationQueries;
use Fundly\Shared\Bus\CommandBus;
use Fundly\Shared\Http\ApiResponse;
use Fundly\Shared\Security\CurrentPrincipal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class NotificationController
{
    public function __construct(private readonly CommandBus $bus, private readonly CurrentPrincipal $principal, private readonly NotificationQueries $queries) {}

    public function inbox(Request $request): JsonResponse
    {
        $filter = $request->query('filter');

        return ApiResponse::json($this->queries->inbox($this->principal->require(), is_array($filter) && ($filter['unread'] ?? null) === 'true'));
    }

    public function read(string $id): JsonResponse
    {
        return ApiResponse::resource($this->bus->dispatch(new MarkNotificationRead($id), $this->principal->require()));
    }

    public function readAll(): JsonResponse
    {
        return ApiResponse::resource($this->bus->dispatch(new MarkAllNotificationsRead, $this->principal->require()));
    }

    public function forApplication(string $id): JsonResponse
    {
        return ApiResponse::json($this->queries->forApplication($id, $this->principal->require()));
    }
}

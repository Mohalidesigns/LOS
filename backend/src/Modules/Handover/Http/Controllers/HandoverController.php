<?php

declare(strict_types=1);

namespace Fundly\Modules\Handover\Http\Controllers;

use Fundly\Modules\Handover\Application\Commands\AcknowledgeHandover;
use Fundly\Modules\Handover\Application\HandoverQueries;
use Fundly\Shared\Bus\CommandBus;
use Fundly\Shared\Http\ApiResponse;
use Fundly\Shared\Security\CurrentPrincipal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class HandoverController
{
    public function __construct(private readonly CommandBus $bus, private readonly CurrentPrincipal $principal, private readonly HandoverQueries $queries) {}

    public function forApplication(string $id): JsonResponse
    {
        return ApiResponse::json($this->queries->forApplication($id, $this->principal->require()));
    }

    public function show(string $id): JsonResponse
    {
        return ApiResponse::json($this->queries->show($id, $this->principal->require()));
    }

    public function acknowledge(Request $request, string $id): JsonResponse
    {
        $note = $request->input('note');

        return ApiResponse::resource($this->bus->dispatch(new AcknowledgeHandover($id, is_string($note) ? trim($note) : null), $this->principal->require()));
    }
}

<?php

declare(strict_types=1);

namespace Fundly\Modules\Platform\Http\Controllers;

use Fundly\Modules\Platform\Application\Commands\OrgUnits\CreateOrgUnit;
use Fundly\Modules\Platform\Application\Commands\OrgUnits\UpdateOrgUnit;
use Fundly\Modules\Platform\Application\Queries\PlatformQueries;
use Fundly\Shared\Bus\CommandBus;
use Fundly\Shared\Http\ApiResponse;
use Fundly\Shared\Security\CurrentPrincipal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class OrgUnitController
{
    public function __construct(
        private readonly CommandBus $bus,
        private readonly CurrentPrincipal $principal,
        private readonly PlatformQueries $queries,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::json($this->queries->orgUnits($request, $this->principal->require()));
    }

    public function show(string $id): JsonResponse
    {
        return ApiResponse::resource($this->queries->orgUnit($id, $this->principal->require()));
    }

    public function store(Request $request): JsonResponse
    {
        $result = $this->bus->dispatch(new CreateOrgUnit(
            (string) $request->input('legal_entity_id', ''),
            $request->filled('parent_id') ? (string) $request->input('parent_id') : null,
            (string) $request->input('code', ''),
            (string) $request->input('name', ''),
        ), $this->principal->require());

        return ApiResponse::resource($result, 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        /** @var array<string, mixed> $changes */
        $changes = $request->only(['name', 'status', 'parent_id']);
        $ifMatch = $request->headers->get('If-Match');

        return ApiResponse::resource($this->bus->dispatch(new UpdateOrgUnit($id, $changes, is_string($ifMatch) ? $ifMatch : null), $this->principal->require()));
    }
}

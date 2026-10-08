<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Http\Controllers;

use Fundly\Modules\Access\Application\Commands\Roles\CloneRole;
use Fundly\Modules\Access\Application\Commands\Roles\CreateRole;
use Fundly\Modules\Access\Application\Commands\Roles\RequestRolePermissions;
use Fundly\Modules\Access\Application\Commands\Roles\UpdateRole;
use Fundly\Modules\Access\Application\Queries\AccessQueries;
use Fundly\Shared\Bus\CommandBus;
use Fundly\Shared\Http\ApiResponse;
use Fundly\Shared\Security\CurrentPrincipal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RoleController
{
    public function __construct(
        private readonly CommandBus $bus,
        private readonly CurrentPrincipal $principal,
        private readonly AccessQueries $queries,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::json($this->queries->roles($request));
    }

    public function show(string $id): JsonResponse
    {
        return ApiResponse::resource($this->queries->role($id));
    }

    public function store(Request $request): JsonResponse
    {
        $result = $this->bus->dispatch(new CreateRole(
            (string) $request->input('code', ''),
            (string) $request->input('name', ''),
            $request->filled('description') ? (string) $request->input('description') : null,
        ), $this->principal->require());

        return ApiResponse::resource($result, 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $ifMatch = $request->headers->get('If-Match');
        $result = $this->bus->dispatch(new UpdateRole(
            $id,
            $request->filled('name') ? (string) $request->input('name') : null,
            $request->filled('description') ? (string) $request->input('description') : null,
            $request->exists('description'),
            is_string($ifMatch) ? $ifMatch : null,
        ), $this->principal->require());

        return ApiResponse::resource($result);
    }

    public function clone(Request $request, string $id): JsonResponse
    {
        $result = $this->bus->dispatch(new CloneRole(
            $id,
            (string) $request->input('code', ''),
            (string) $request->input('name', ''),
            $request->filled('description') ? (string) $request->input('description') : null,
        ), $this->principal->require());

        return ApiResponse::resource($result, 201);
    }

    public function setPermissions(Request $request, string $id): JsonResponse
    {
        /** @var list<string> $permissions */
        $permissions = array_values(array_filter((array) $request->input('permissions', []), 'is_string'));

        return ApiResponse::changeRequest($this->bus->dispatch(
            new RequestRolePermissions($id, $permissions, $request->filled('reason') ? (string) $request->input('reason') : null),
            $this->principal->require(),
        ));
    }

    public function permissions(): JsonResponse
    {
        return ApiResponse::json(['data' => $this->queries->permissions()]);
    }
}

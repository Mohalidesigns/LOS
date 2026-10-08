<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Http\Controllers;

use Fundly\Modules\Access\Application\Commands\Delegations\CreateDelegation;
use Fundly\Modules\Access\Application\Commands\Delegations\RevokeDelegation;
use Fundly\Modules\Access\Application\Queries\AccessQueries;
use Fundly\Shared\Bus\CommandBus;
use Fundly\Shared\Http\ApiResponse;
use Fundly\Shared\Security\CurrentPrincipal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DelegationController
{
    public function __construct(
        private readonly CommandBus $bus,
        private readonly CurrentPrincipal $principal,
        private readonly AccessQueries $queries,
    ) {
    }

    /** Delegations the caller gave or received. */
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::json($this->queries->delegations($request, $this->principal->require()));
    }

    public function store(Request $request): JsonResponse
    {
        $result = $this->bus->dispatch(new CreateDelegation(
            (string) $request->input('role_assignment_id', ''),
            (string) $request->input('delegate_id', ''),
            (string) $request->input('valid_from', now()->toIso8601ZuluString()),
            $request->filled('valid_to') ? (string) $request->input('valid_to') : null,
            (string) $request->input('reason', ''),
        ), $this->principal->require());

        return ApiResponse::resource($result, 201);
    }

    public function destroy(string $id): JsonResponse
    {
        return ApiResponse::resource($this->bus->dispatch(new RevokeDelegation($id), $this->principal->require()));
    }
}

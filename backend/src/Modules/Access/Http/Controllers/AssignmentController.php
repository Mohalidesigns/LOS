<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Http\Controllers;

use Fundly\Modules\Access\Application\Commands\Assignments\RequestAssignmentRevocation;
use Fundly\Modules\Access\Application\Commands\Assignments\RequestRoleAssignment;
use Fundly\Modules\Access\Application\Queries\AccessQueries;
use Fundly\Shared\Bus\CommandBus;
use Fundly\Shared\Http\ApiResponse;
use Fundly\Shared\Security\CurrentPrincipal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AssignmentController
{
    public function __construct(
        private readonly CommandBus $bus,
        private readonly CurrentPrincipal $principal,
        private readonly AccessQueries $queries,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::json($this->queries->assignments($request, $this->principal->require()));
    }

    /** Raises a maker-checker request; the assignment exists only once a checker approves. */
    public function store(Request $request): JsonResponse
    {
        $scope = $request->input('scope', []);

        return ApiResponse::changeRequest($this->bus->dispatch(new RequestRoleAssignment(
            userId: (string) $request->input('user_id', ''),
            roleId: (string) $request->input('role_id', ''),
            scope: is_array($scope) ? $scope : [],
            validFrom: (string) $request->input('valid_from', now()->toIso8601ZuluString()),
            validTo: $request->filled('valid_to') ? (string) $request->input('valid_to') : null,
            reason: $request->filled('reason') ? (string) $request->input('reason') : null,
        ), $this->principal->require()));
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        return ApiResponse::changeRequest($this->bus->dispatch(
            new RequestAssignmentRevocation($id, $request->filled('reason') ? (string) $request->input('reason') : null),
            $this->principal->require(),
        ));
    }
}

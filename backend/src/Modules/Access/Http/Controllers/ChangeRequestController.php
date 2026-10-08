<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Http\Controllers;

use Fundly\Modules\Access\Application\ChangeRequests\ChangeActionRegistry;
use Fundly\Modules\Access\Application\Commands\ChangeRequests\DecideChangeRequest;
use Fundly\Modules\Access\Application\Queries\AccessQueries;
use Fundly\Shared\Bus\CommandBus;
use Fundly\Shared\Exceptions\CodedConflict;
use Fundly\Shared\Http\ApiResponse;
use Fundly\Shared\Security\CurrentPrincipal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ChangeRequestController
{
    public function __construct(
        private readonly CommandBus $bus,
        private readonly CurrentPrincipal $principal,
        private readonly AccessQueries $queries,
        private readonly ChangeActionRegistry $registry,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::json($this->queries->changeRequests($request));
    }

    public function show(string $id): JsonResponse
    {
        return ApiResponse::json(['data' => $this->queries->changeRequest($id)]);
    }

    public function approve(Request $request, string $id): JsonResponse
    {
        return $this->decide($request, $id, DecideChangeRequest::APPROVE, $this->queries->changeRequestCheckerPermission($id));
    }

    public function reject(Request $request, string $id): JsonResponse
    {
        return $this->decide($request, $id, DecideChangeRequest::REJECT, $this->queries->changeRequestCheckerPermission($id));
    }

    public function cancel(Request $request, string $id): JsonResponse
    {
        return $this->decide($request, $id, DecideChangeRequest::CANCEL, $this->queries->changeRequestMakerPermission($id, $this->registry));
    }

    private function decide(Request $request, string $id, string $decision, string $permission): JsonResponse
    {
        /** @var array<string, mixed> $result */
        $result = $this->bus->dispatch(new DecideChangeRequest(
            $id,
            $decision,
            $request->filled('reason') ? (string) $request->input('reason') : null,
            $permission,
        ), $this->principal->require());

        // The failed state is committed (it is part of the record); the caller gets a 409.
        if (in_array($result['status'] ?? null, ['failed_stale', 'failed'], true)) {
            throw new CodedConflict(
                $result['status'] === 'failed_stale' ? 'change-request-stale' : 'change-request-invalid',
                'The change request could not be executed and has been closed.',
                ['change_request' => $result],
            );
        }

        return ApiResponse::json(['data' => $result]);
    }
}

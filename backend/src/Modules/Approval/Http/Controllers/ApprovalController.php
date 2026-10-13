<?php

declare(strict_types=1);

namespace Fundly\Modules\Approval\Http\Controllers;

use Fundly\Modules\Approval\Application\ApprovalQueries;
use Fundly\Modules\Approval\Application\Commands\CastApprovalVote;
use Fundly\Shared\Bus\CommandBus;
use Fundly\Shared\Http\ApiResponse;
use Fundly\Shared\Security\CurrentPrincipal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ApprovalController
{
    public function __construct(private readonly CommandBus $bus, private readonly CurrentPrincipal $principal, private readonly ApprovalQueries $queries) {}

    public function forApplication(string $id): JsonResponse
    {
        return ApiResponse::json($this->queries->forApplication($id, $this->principal->require()));
    }

    public function inbox(): JsonResponse
    {
        return ApiResponse::json($this->queries->inbox($this->principal->require()));
    }

    public function show(string $id): JsonResponse
    {
        return ApiResponse::json($this->queries->show($id, $this->principal->require()));
    }

    public function vote(Request $request, string $id): JsonResponse
    {
        $codes = $request->input('reason_codes', []);
        $conditions = $request->input('conditions', []);
        $counter = $request->input('counter_terms');
        $rationale = $request->input('rationale');

        return ApiResponse::resource($this->bus->dispatch(new CastApprovalVote(
            requestId: $id,
            decision: (string) $request->input('decision', ''),
            rationale: is_string($rationale) ? $rationale : null,
            reasonCodes: is_array($codes) ? array_values($codes) : [],
            conditions: is_array($conditions) ? array_values($conditions) : [],
            counterTerms: is_array($counter) ? $counter : null,
        ), $this->principal->require()));
    }
}

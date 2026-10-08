<?php

declare(strict_types=1);

namespace Fundly\Modules\Compliance\Http\Controllers;

use Fundly\Modules\Compliance\Application\Commands\DispositionAlert;
use Fundly\Modules\Compliance\Application\Commands\ScreenApplication;
use Fundly\Modules\Compliance\Application\ComplianceQueries;
use Fundly\Shared\Bus\CommandBus;
use Fundly\Shared\Http\ApiResponse;
use Fundly\Shared\Security\CurrentPrincipal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ComplianceController
{
    public function __construct(
        private readonly CommandBus $bus,
        private readonly CurrentPrincipal $principal,
        private readonly ComplianceQueries $queries,
    ) {}

    public function alerts(Request $request): JsonResponse
    {
        return ApiResponse::json($this->queries->alerts($request, $this->principal->require()));
    }

    public function alert(string $id): JsonResponse
    {
        return ApiResponse::json($this->queries->alert($id, $this->principal->require()));
    }

    public function disposition(Request $request, string $id, string $step): JsonResponse
    {
        $decision = $request->input('decision');
        $reason = $request->input('reason');
        $evidence = $request->input('evidence_ref');

        return ApiResponse::resource($this->bus->dispatch(new DispositionAlert(
            alertId: $id,
            step: $step,
            decision: is_string($decision) ? $decision : null,
            reason: is_string($reason) ? $reason : null,
            evidenceRef: is_string($evidence) ? $evidence : null,
        ), $this->principal->require()));
    }

    public function kyc(string $id): JsonResponse
    {
        return ApiResponse::json($this->queries->kycStatus($id, $this->principal->require()));
    }

    /** Manual re-screen by a compliance officer; the gate re-evaluates afterwards. */
    public function rescreen(string $id): JsonResponse
    {
        $principal = $this->principal->require();
        $this->bus->dispatch(new ScreenApplication($id, 'manual', manual: true), $principal);

        return ApiResponse::json($this->queries->kycStatus($id, $principal));
    }
}

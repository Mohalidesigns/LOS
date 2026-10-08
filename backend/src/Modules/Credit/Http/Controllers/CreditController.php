<?php

declare(strict_types=1);

namespace Fundly\Modules\Credit\Http\Controllers;

use Fundly\Modules\Credit\Application\Commands\PullBureauReport;
use Fundly\Modules\Credit\Application\Commands\RaisePolicyException;
use Fundly\Modules\Credit\Application\Commands\ReplayDecision;
use Fundly\Modules\Credit\Application\Commands\RunDecision;
use Fundly\Modules\Credit\Application\Commands\SaveCreditMemo;
use Fundly\Modules\Credit\Application\CreditQueries;
use Fundly\Shared\Bus\CommandBus;
use Fundly\Shared\Http\ApiResponse;
use Fundly\Shared\Security\CurrentPrincipal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CreditController
{
    public function __construct(private readonly CommandBus $bus, private readonly CurrentPrincipal $principal, private readonly CreditQueries $queries) {}

    public function bureauReports(string $id): JsonResponse
    {
        return ApiResponse::json($this->queries->bureauReports($id, $this->principal->require()));
    }

    public function pullBureau(Request $request, string $id): JsonResponse
    {
        return ApiResponse::resource($this->bus->dispatch(new PullBureauReport($id, self::str($request->input('party_id'))), $this->principal->require()), 201);
    }

    public function decisions(string $id): JsonResponse
    {
        return ApiResponse::json($this->queries->decisions($id, $this->principal->require()));
    }

    public function runDecision(string $id): JsonResponse
    {
        return ApiResponse::resource($this->bus->dispatch(new RunDecision($id), $this->principal->require()), 201);
    }

    public function decision(string $id): JsonResponse
    {
        return ApiResponse::json($this->queries->decision($id, $this->principal->require()));
    }

    public function replay(string $id): JsonResponse
    {
        return ApiResponse::resource($this->bus->dispatch(new ReplayDecision($id), $this->principal->require()));
    }

    public function raiseException(Request $request, string $id): JsonResponse
    {
        return ApiResponse::resource($this->bus->dispatch(new RaisePolicyException(
            $id, (string) $request->input('reason_code', ''), self::str($request->input('justification')), self::str($request->input('evidence_ref')), (string) $request->input('severity', ''),
        ), $this->principal->require()), 201);
    }

    public function exceptions(string $id): JsonResponse
    {
        return ApiResponse::json($this->queries->exceptions($id, $this->principal->require()));
    }

    public function memo(string $id): JsonResponse
    {
        return ApiResponse::json($this->queries->memo($id, $this->principal->require()));
    }

    public function saveMemo(Request $request, string $id): JsonResponse
    {
        $tenor = $request->input('recommended_tenor_months');
        $conditions = $request->input('conditions', []);

        return ApiResponse::resource($this->bus->dispatch(new SaveCreditMemo(
            applicationId: $id,
            narrative: self::str($request->input('narrative')),
            recommendation: (string) $request->input('recommendation', ''),
            recommendedAmount: self::str($request->input('recommended_amount')),
            recommendedTenorMonths: is_int($tenor) ? $tenor : null,
            conditions: is_array($conditions) ? array_values(array_filter($conditions, 'is_string')) : [],
        ), $this->principal->require()), 201);
    }

    private static function str(mixed $v): ?string
    {
        return is_string($v) && trim($v) !== '' ? trim($v) : null;
    }
}

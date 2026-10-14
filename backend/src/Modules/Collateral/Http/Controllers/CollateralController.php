<?php

declare(strict_types=1);

namespace Fundly\Modules\Collateral\Http\Controllers;

use Fundly\Modules\Collateral\Application\CollateralQueries;
use Fundly\Modules\Collateral\Application\Commands\AllocateCollateral;
use Fundly\Modules\Collateral\Application\Commands\CompletePerfectionStep;
use Fundly\Modules\Collateral\Application\Commands\CreateCollateral;
use Fundly\Modules\Collateral\Application\Commands\RecordInsurance;
use Fundly\Modules\Collateral\Application\Commands\RecordValuation;
use Fundly\Modules\Collateral\Application\Commands\RequestCollateralWaiver;
use Fundly\Modules\Collateral\Application\Commands\UpdatePerfectionStep;
use Fundly\Shared\Bus\CommandBus;
use Fundly\Shared\Http\ApiResponse;
use Fundly\Shared\Security\CurrentPrincipal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

final class CollateralController
{
    public function __construct(private readonly CommandBus $bus, private readonly CurrentPrincipal $principal, private readonly CollateralQueries $queries) {}

    public function types(): JsonResponse
    {
        $this->principal->require();

        return ApiResponse::json($this->queries->types());
    }

    public function forApplication(string $id): JsonResponse
    {
        return ApiResponse::json($this->queries->forApplication($id, $this->principal->require()));
    }

    public function index(Request $request): JsonResponse
    {
        $filter = (array) $request->query('filter', []);

        return ApiResponse::json($this->queries->list($this->principal->require(), self::str($filter['owner_party_id'] ?? null), self::str($filter['application_id'] ?? null)));
    }

    public function show(string $id): JsonResponse
    {
        return ApiResponse::json($this->queries->show($id, $this->principal->require()));
    }

    public function store(Request $request): JsonResponse
    {
        return ApiResponse::resource($this->bus->dispatch(new CreateCollateral(
            (string) $request->input('application_id', ''), self::str($request->input('type_code')), self::str($request->input('description')), self::str($request->input('market_value')),
            self::str($request->input('owner_party_id')), self::str($request->input('owner_name')), self::str($request->input('state_code')),
        ), $this->principal->require()), 201);
    }

    public function valuation(Request $request, string $id): JsonResponse
    {
        [$path, $name] = self::upload($request);

        return ApiResponse::resource($this->bus->dispatch(new RecordValuation(
            $id, self::str($request->input('valuer')), self::str($request->input('valuation_date')), self::str($request->input('method')), self::str($request->input('market_value')),
            self::str($request->input('forced_sale_value')), self::str($request->input('valid_until')), self::str($request->input('note')), $path, $name,
        ), $this->principal->require()), 201);
    }

    public function insurance(Request $request, string $id): JsonResponse
    {
        [$path, $name] = self::upload($request);

        return ApiResponse::resource($this->bus->dispatch(new RecordInsurance(
            $id, self::str($request->input('policy_no')), self::str($request->input('insurer')), self::str($request->input('sum_insured')),
            self::str($request->input('start_date')), self::str($request->input('expiry_date')), $request->boolean('bank_interest_noted'), $path, $name,
        ), $this->principal->require()), 201);
    }

    public function allocate(Request $request, string $id): JsonResponse
    {
        return ApiResponse::resource($this->bus->dispatch(new AllocateCollateral($id, self::str($request->input('application_id')), self::str($request->input('amount'))), $this->principal->require()));
    }

    public function requestInsuranceWaiver(Request $request, string $id): JsonResponse
    {
        return ApiResponse::resource($this->bus->dispatch(new RequestCollateralWaiver('insurance', $id, self::str($request->input('reason'))), $this->principal->require()), 202);
    }

    public function updateStep(Request $request, string $id): JsonResponse
    {
        return ApiResponse::resource($this->bus->dispatch(new UpdatePerfectionStep($id, self::str($request->input('responsible_user_id')), self::str($request->input('due_date')), $request->boolean('start')), $this->principal->require()));
    }

    public function completeStep(Request $request, string $id): JsonResponse
    {
        [$path, $name] = self::upload($request);

        return ApiResponse::resource($this->bus->dispatch(new CompletePerfectionStep($id, self::str($request->input('note')), self::str($request->input('evidence_reference')), $path, $name), $this->principal->require()));
    }

    public function requestStepWaiver(Request $request, string $id): JsonResponse
    {
        return ApiResponse::resource($this->bus->dispatch(new RequestCollateralWaiver('perfection_step', $id, self::str($request->input('reason'))), $this->principal->require()), 202);
    }

    /** @return array{0: ?string, 1: ?string} */
    private static function upload(Request $request): array
    {
        $file = $request->file('file');
        $ok = $file instanceof UploadedFile && $file->isValid();

        return [$ok ? (string) $file->getRealPath() : null, $ok ? $file->getClientOriginalName() : null];
    }

    private static function str(mixed $v): ?string
    {
        return is_string($v) && trim($v) !== '' ? trim($v) : null;
    }
}

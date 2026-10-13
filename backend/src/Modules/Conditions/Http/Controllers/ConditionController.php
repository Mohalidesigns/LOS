<?php

declare(strict_types=1);

namespace Fundly\Modules\Conditions\Http\Controllers;

use Fundly\Modules\Conditions\Application\Commands\AddCondition;
use Fundly\Modules\Conditions\Application\Commands\RequestConditionWaiver;
use Fundly\Modules\Conditions\Application\Commands\RunPreDisbursementCheck;
use Fundly\Modules\Conditions\Application\Commands\SatisfyCondition;
use Fundly\Modules\Conditions\Application\ConditionQueries;
use Fundly\Shared\Bus\CommandBus;
use Fundly\Shared\Http\ApiResponse;
use Fundly\Shared\Security\CurrentPrincipal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

final class ConditionController
{
    public function __construct(private readonly CommandBus $bus, private readonly CurrentPrincipal $principal, private readonly ConditionQueries $queries) {}

    public function forApplication(string $id): JsonResponse
    {
        return ApiResponse::json($this->queries->forApplication($id, $this->principal->require()));
    }

    public function add(Request $request, string $id): JsonResponse
    {
        return ApiResponse::resource($this->bus->dispatch(new AddCondition(
            $id, (string) $request->input('kind', ''), self::str($request->input('description')), $request->boolean('mandatory', true),
            self::str($request->input('owner_id')), self::str($request->input('due_date')),
        ), $this->principal->require()), 201);
    }

    public function satisfy(Request $request, string $id): JsonResponse
    {
        $file = $request->file('file');
        $ok = $file instanceof UploadedFile && $file->isValid();

        return ApiResponse::resource($this->bus->dispatch(new SatisfyCondition(
            $id, self::str($request->input('note')), self::str($request->input('evidence_reference')),
            $ok ? (string) $file->getRealPath() : null, $ok ? $file->getClientOriginalName() : null,
        ), $this->principal->require()));
    }

    public function requestWaiver(Request $request, string $id): JsonResponse
    {
        return ApiResponse::resource($this->bus->dispatch(new RequestConditionWaiver($id, self::str($request->input('reason'))), $this->principal->require()), 202);
    }

    public function check(Request $request, string $id): JsonResponse
    {
        return ApiResponse::resource($this->bus->dispatch(new RunPreDisbursementCheck($id, (string) $request->input('bank_code', ''), (string) $request->input('account_no', '')), $this->principal->require()), 201);
    }

    private static function str(mixed $v): ?string
    {
        return is_string($v) && trim($v) !== '' ? trim($v) : null;
    }
}

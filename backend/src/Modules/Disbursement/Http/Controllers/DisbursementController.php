<?php

declare(strict_types=1);

namespace Fundly\Modules\Disbursement\Http\Controllers;

use Fundly\Modules\Disbursement\Application\Commands\CancelDisbursement;
use Fundly\Modules\Disbursement\Application\Commands\CreateDisbursement;
use Fundly\Modules\Disbursement\Application\Commands\ResolveBreak;
use Fundly\Modules\Disbursement\Application\Commands\RetryDisbursement;
use Fundly\Modules\Disbursement\Application\Commands\RunReconciliation;
use Fundly\Modules\Disbursement\Application\DisbursementQueries;
use Fundly\Shared\Bus\CommandBus;
use Fundly\Shared\Http\ApiResponse;
use Fundly\Shared\Security\CurrentPrincipal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DisbursementController
{
    public function __construct(private readonly CommandBus $bus, private readonly CurrentPrincipal $principal, private readonly DisbursementQueries $queries) {}

    public function forApplication(string $id): JsonResponse
    {
        return ApiResponse::json($this->queries->forApplication($id, $this->principal->require()));
    }

    public function create(Request $request, string $id): JsonResponse
    {
        $v = $request->input('value_date');

        return ApiResponse::resource($this->bus->dispatch(new CreateDisbursement($id, is_string($v) && $v !== '' ? $v : null), $this->principal->require()), 201);
    }

    public function cancel(Request $request, string $id): JsonResponse
    {
        $r = $request->input('reason');

        return ApiResponse::resource($this->bus->dispatch(new CancelDisbursement($id, is_string($r) ? trim($r) : null), $this->principal->require()));
    }

    public function retry(string $id): JsonResponse
    {
        return ApiResponse::resource($this->bus->dispatch(new RetryDisbursement($id), $this->principal->require()));
    }

    public function exceptions(): JsonResponse
    {
        return ApiResponse::json($this->queries->exceptions());
    }

    public function runs(): JsonResponse
    {
        return ApiResponse::json($this->queries->runs());
    }

    public function reconcile(Request $request): JsonResponse
    {
        $from = $request->input('from');
        $to = $request->input('to');

        return ApiResponse::resource($this->bus->dispatch(new RunReconciliation(is_string($from) && $from !== '' ? $from : null, is_string($to) && $to !== '' ? $to : null), $this->principal->require()), 201);
    }

    public function breaks(): JsonResponse
    {
        return ApiResponse::json($this->queries->openBreaks());
    }

    public function resolve(Request $request, string $id): JsonResponse
    {
        $n = $request->input('note');

        return ApiResponse::resource($this->bus->dispatch(new ResolveBreak($id, is_string($n) ? trim($n) : null), $this->principal->require()));
    }
}

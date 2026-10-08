<?php

declare(strict_types=1);

namespace Fundly\Modules\Audit\Http\Controllers;

use Fundly\Modules\Audit\Application\AuditQueries;
use Fundly\Shared\Audit\AuditVerifier;
use Fundly\Shared\Http\ApiResponse;
use Fundly\Shared\Security\CurrentPrincipal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AuditController
{
    public function __construct(
        private readonly AuditQueries $queries,
        private readonly AuditVerifier $verifier,
        private readonly CurrentPrincipal $principal,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::json($this->queries->search($request));
    }

    public function verify(): JsonResponse
    {
        return ApiResponse::json(['data' => $this->verifier->verify($this->principal->require()->tenantId)->toArray()]);
    }
}

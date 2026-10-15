<?php

declare(strict_types=1);

namespace Fundly\Modules\Audit\Http\Controllers;

use Fundly\Modules\Audit\Application\AuditQueries;
use Fundly\Modules\Audit\Application\EvidencePackBuilder;
use Fundly\Modules\Audit\Application\OverridesReport;
use Fundly\Modules\Audit\Application\ReadAccessLog;
use Fundly\Shared\Audit\AuditVerifier;
use Fundly\Shared\Http\ApiResponse;
use Fundly\Shared\Security\CurrentPrincipal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

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

    public function readAccess(Request $request, ReadAccessLog $log): JsonResponse
    {
        return ApiResponse::json($log->search($request));
    }

    public function overrides(Request $request, OverridesReport $report): JsonResponse
    {
        return ApiResponse::json($report->search($request));
    }

    public function evidencePack(string $id, EvidencePackBuilder $builder): Response
    {
        $pack = $builder->build($id, $this->principal->require());

        return new Response($pack['bytes'], 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="'.addcslashes($pack['filename'], '"\\').'"',
            'X-Content-Type-Options' => 'nosniff',
            'X-Manifest-SHA256' => $pack['manifest_sha256'],
            'Cache-Control' => 'no-store',
        ]);
    }
}

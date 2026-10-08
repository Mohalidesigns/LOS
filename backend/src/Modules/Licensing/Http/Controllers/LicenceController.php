<?php

declare(strict_types=1);

namespace Fundly\Modules\Licensing\Http\Controllers;

use Fundly\Integration\Ports\Licensing\SignedLicence;
use Fundly\Modules\Licensing\Application\LicenceStatusQuery;
use Fundly\Modules\Licensing\Application\RequestLicenceImport;
use Fundly\Shared\Bus\CommandBus;
use Fundly\Shared\Exceptions\ValidationFailed;
use Fundly\Shared\Http\ApiResponse;
use Fundly\Shared\Security\CurrentPrincipal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LicenceController
{
    public function __construct(
        private readonly LicenceStatusQuery $status,
        private readonly CommandBus $bus,
        private readonly CurrentPrincipal $principal,
    ) {}

    public function show(): JsonResponse
    {
        return ApiResponse::json(['data' => $this->status->status()]);
    }

    /** Body: the licence file ({"licence": {...}, "signature": "..."}). Raises a change request. */
    public function import(Request $request): JsonResponse
    {
        $licence = $request->input('licence');
        $signature = $request->input('signature');
        if (! is_array($licence) || ! is_string($signature)) {
            throw ValidationFailed::with(['licence' => 'Provide the licence file contents: {"licence": {...}, "signature": "..."}.']);
        }
        $signed = SignedLicence::fromFileContents((string) json_encode(['licence' => $licence, 'signature' => $signature]));

        return ApiResponse::changeRequest($this->bus->dispatch(
            new RequestLicenceImport($signed->document, $signed->signature, $request->filled('reason') ? (string) $request->input('reason') : null),
            $this->principal->require(),
        ));
    }

    public function activationRequest(): JsonResponse
    {
        return ApiResponse::json(['data' => $this->status->activationRequest()]);
    }
}

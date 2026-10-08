<?php

declare(strict_types=1);

namespace Fundly\Modules\Platform\Http\Controllers;

use Fundly\Modules\Platform\Application\Commands\LegalEntities\CreateLegalEntity;
use Fundly\Modules\Platform\Application\Commands\LegalEntities\UpdateLegalEntity;
use Fundly\Modules\Platform\Application\Queries\PlatformQueries;
use Fundly\Shared\Bus\CommandBus;
use Fundly\Shared\Http\ApiResponse;
use Fundly\Shared\Security\CurrentPrincipal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LegalEntityController
{
    public function __construct(
        private readonly CommandBus $bus,
        private readonly CurrentPrincipal $principal,
        private readonly PlatformQueries $queries,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::json($this->queries->legalEntities($request, $this->principal->require()));
    }

    public function show(string $id): JsonResponse
    {
        return ApiResponse::resource($this->queries->legalEntity($id, $this->principal->require()));
    }

    public function store(Request $request): JsonResponse
    {
        $labels = $request->input('org_level_labels', []);
        $result = $this->bus->dispatch(new CreateLegalEntity(
            code: (string) $request->input('code', ''),
            name: (string) $request->input('name', ''),
            jurisdiction: (string) $request->input('jurisdiction', ''),
            licenceCategory: (string) $request->input('licence_category', ''),
            baseCurrency: (string) $request->input('base_currency', ''),
            timezone: (string) $request->input('timezone', ''),
            orgLevelLabels: is_array($labels) ? array_values(array_map('strval', array_filter($labels, 'is_scalar'))) : [],
        ), $this->principal->require());

        return ApiResponse::resource($result, 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        /** @var array<string, mixed> $changes */
        $changes = $request->only(['name', 'timezone', 'org_level_labels', 'status']);
        $ifMatch = $request->headers->get('If-Match');

        return ApiResponse::resource($this->bus->dispatch(new UpdateLegalEntity($id, $changes, is_string($ifMatch) ? $ifMatch : null), $this->principal->require()));
    }
}

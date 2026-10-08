<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Http\Controllers;

use Fundly\Modules\Access\Application\Commands\Sod\CreateSodRule;
use Fundly\Modules\Access\Application\Commands\Sod\DeleteSodRule;
use Fundly\Modules\Access\Application\Queries\AccessQueries;
use Fundly\Shared\Bus\CommandBus;
use Fundly\Shared\Http\ApiResponse;
use Fundly\Shared\Security\CurrentPrincipal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SodController
{
    public function __construct(
        private readonly CommandBus $bus,
        private readonly CurrentPrincipal $principal,
        private readonly AccessQueries $queries,
    ) {}

    public function index(): JsonResponse
    {
        return ApiResponse::json(['data' => $this->queries->sodRules()]);
    }

    public function store(Request $request): JsonResponse
    {
        $result = $this->bus->dispatch(new CreateSodRule(
            (string) $request->input('kind', ''),
            (string) $request->input('left', ''),
            (string) $request->input('right', ''),
            (string) $request->input('description', ''),
        ), $this->principal->require());

        return ApiResponse::resource($result, 201);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->bus->dispatch(new DeleteSodRule($id), $this->principal->require());

        return ApiResponse::noContent();
    }

    public function conflicts(): JsonResponse
    {
        return ApiResponse::json(['data' => $this->queries->sodConflicts()]);
    }
}

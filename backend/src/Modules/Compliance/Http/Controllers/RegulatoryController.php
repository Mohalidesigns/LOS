<?php

declare(strict_types=1);

namespace Fundly\Modules\Compliance\Http\Controllers;

use Fundly\Modules\Compliance\Application\Packs\PackQueries;
use Fundly\Shared\Http\ApiResponse;
use Fundly\Shared\Security\CurrentPrincipal;
use Illuminate\Http\JsonResponse;

final class RegulatoryController
{
    public function __construct(private readonly CurrentPrincipal $principal, private readonly PackQueries $queries) {}

    public function register(string $id): JsonResponse
    {
        return ApiResponse::json($this->queries->register($id, $this->principal->require()));
    }

    public function defaults(): JsonResponse
    {
        $this->principal->require();

        return ApiResponse::json($this->queries->defaults());
    }

    public function limitChecks(string $id): JsonResponse
    {
        return ApiResponse::json($this->queries->limitChecks($id, $this->principal->require()));
    }
}

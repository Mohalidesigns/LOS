<?php

declare(strict_types=1);

namespace Fundly\Integration\Runtime\Http;

use Fundly\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class IntegrationController
{
    public function __construct(private readonly IntegrationQueries $queries)
    {
    }

    public function bindings(Request $request): JsonResponse
    {
        return ApiResponse::json($this->queries->bindings($request));
    }

    public function binding(string $id): JsonResponse
    {
        return ApiResponse::json(['data' => $this->queries->binding($id)]);
    }

    public function calls(Request $request): JsonResponse
    {
        return ApiResponse::json($this->queries->calls($request));
    }
}

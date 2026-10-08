<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Http\Controllers;

use Fundly\Modules\Access\Application\Commands\Users\CreateUser;
use Fundly\Modules\Access\Application\Commands\Users\IssueServiceToken;
use Fundly\Modules\Access\Application\Commands\Users\UpdateUser;
use Fundly\Modules\Access\Application\Queries\AccessQueries;
use Fundly\Modules\Access\Application\Queries\EffectiveAccessQuery;
use Fundly\Shared\Bus\CommandBus;
use Fundly\Shared\Http\ApiResponse;
use Fundly\Shared\Security\CurrentPrincipal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class UserController
{
    public function __construct(
        private readonly CommandBus $bus,
        private readonly CurrentPrincipal $principal,
        private readonly AccessQueries $queries,
        private readonly EffectiveAccessQuery $effective,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::json($this->queries->users($request, $this->principal->require()));
    }

    public function show(string $id): JsonResponse
    {
        return ApiResponse::resource($this->queries->user($id, $this->principal->require()));
    }

    public function store(Request $request): JsonResponse
    {
        $result = $this->bus->dispatch(new CreateUser(
            kind: (string) $request->input('kind', ''),
            email: (string) $request->input('email', ''),
            name: (string) $request->input('name', ''),
            password: $request->filled('password') ? (string) $request->input('password') : null,
            homeLegalEntityId: $request->filled('home_legal_entity_id') ? (string) $request->input('home_legal_entity_id') : null,
            homeOrgUnitId: $request->filled('home_org_unit_id') ? (string) $request->input('home_org_unit_id') : null,
        ), $this->principal->require());

        return ApiResponse::resource($result, 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        /** @var array<string, mixed> $changes */
        $changes = $request->only(['name', 'status', 'home_org_unit_id']);
        $ifMatch = $request->headers->get('If-Match');
        $result = $this->bus->dispatch(new UpdateUser($id, $changes, is_string($ifMatch) ? $ifMatch : null), $this->principal->require());

        return ApiResponse::resource($result);
    }

    public function effectiveAccess(string $id): JsonResponse
    {
        $this->queries->user($id, $this->principal->require());

        return ApiResponse::json(['data' => $this->effective->forUser($id)]);
    }

    public function issueToken(Request $request, string $id): JsonResponse
    {
        /** @var list<string> $abilities */
        $abilities = array_values(array_filter((array) $request->input('abilities', []), 'is_string'));
        $result = $this->bus->dispatch(new IssueServiceToken(
            $id,
            (string) $request->input('name', ''),
            $abilities,
            $request->filled('expires_at') ? (string) $request->input('expires_at') : null,
        ), $this->principal->require());

        return ApiResponse::json(['data' => $result], 201);
    }
}

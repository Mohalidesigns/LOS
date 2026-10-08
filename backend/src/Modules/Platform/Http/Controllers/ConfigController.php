<?php

declare(strict_types=1);

namespace Fundly\Modules\Platform\Http\Controllers;

use Fundly\Modules\Platform\Application\Commands\Config\CreateConfigArtifact;
use Fundly\Modules\Platform\Application\Commands\Config\CreateConfigVersion;
use Fundly\Modules\Platform\Application\Commands\Config\RequestConfigActivation;
use Fundly\Modules\Platform\Application\Commands\Config\TransitionConfigVersion;
use Fundly\Modules\Platform\Application\Commands\Config\UpdateConfigDraft;
use Fundly\Modules\Platform\Application\Queries\PlatformQueries;
use Fundly\Shared\Bus\CommandBus;
use Fundly\Shared\Http\ApiResponse;
use Fundly\Shared\Security\CurrentPrincipal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** /config-artifacts/{type}/{artifact}/versions/{version} lifecycle (TRD §10.2, FR-TEN-009). */
final class ConfigController
{
    public function __construct(
        private readonly CommandBus $bus,
        private readonly CurrentPrincipal $principal,
        private readonly PlatformQueries $queries,
    ) {
    }

    public function index(Request $request, string $type): JsonResponse
    {
        return ApiResponse::json($this->queries->configArtifacts($type, $request));
    }

    public function store(Request $request, string $type): JsonResponse
    {
        $result = $this->bus->dispatch(new CreateConfigArtifact(
            $type,
            (string) $request->input('key', ''),
            (string) $request->input('name', ''),
            $request->filled('description') ? (string) $request->input('description') : null,
        ), $this->principal->require());

        return ApiResponse::resource($result, 201);
    }

    public function show(string $type, string $artifact): JsonResponse
    {
        return ApiResponse::json(['data' => $this->queries->configArtifact($type, $artifact)]);
    }

    public function versions(Request $request, string $type, string $artifact): JsonResponse
    {
        return ApiResponse::json($this->queries->configVersions($type, $artifact, $request));
    }

    public function storeVersion(Request $request, string $type, string $artifact): JsonResponse
    {
        $this->queries->configArtifact($type, $artifact);
        $content = $request->input('content');
        $result = $this->bus->dispatch(new CreateConfigVersion(
            $artifact,
            is_array($content) ? $content : [],
            $request->filled('notes') ? (string) $request->input('notes') : null,
        ), $this->principal->require());

        return ApiResponse::resource($result, 201);
    }

    public function showVersion(string $type, string $artifact, string $version): JsonResponse
    {
        return ApiResponse::resource($this->queries->configVersion($type, $artifact, $version));
    }

    public function updateVersion(Request $request, string $type, string $artifact, string $version): JsonResponse
    {
        $this->queries->configVersion($type, $artifact, $version);
        $content = $request->input('content');
        $ifMatch = $request->headers->get('If-Match');

        return ApiResponse::resource($this->bus->dispatch(new UpdateConfigDraft(
            $version,
            is_array($content) ? $content : [],
            $request->filled('notes') ? (string) $request->input('notes') : null,
            is_string($ifMatch) ? $ifMatch : null,
        ), $this->principal->require()));
    }

    public function transition(Request $request, string $type, string $artifact, string $version, string $action): JsonResponse
    {
        $this->queries->configVersion($type, $artifact, $version);
        if (in_array($action, ['activate', 'rollback'], true)) {
            return ApiResponse::changeRequest($this->bus->dispatch(new RequestConfigActivation(
                $version,
                $action === 'rollback',
                $request->filled('reason') ? (string) $request->input('reason') : null,
            ), $this->principal->require()));
        }

        return ApiResponse::resource($this->bus->dispatch(new TransitionConfigVersion(
            $version,
            $action,
            $request->filled('reason') ? (string) $request->input('reason') : null,
        ), $this->principal->require()));
    }
}

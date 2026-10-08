<?php

declare(strict_types=1);

namespace Fundly\Modules\Document\Http\Controllers;

use Fundly\Modules\Document\Application\Commands\RequestChecklistWaiver;
use Fundly\Modules\Document\Application\Commands\ReviewChecklistItem;
use Fundly\Modules\Document\Application\Commands\UploadDocument;
use Fundly\Modules\Document\Application\DocumentQueries;
use Fundly\Shared\Bus\CommandBus;
use Fundly\Shared\Exceptions\ValidationFailed;
use Fundly\Shared\Http\ApiResponse;
use Fundly\Shared\Security\CurrentPrincipal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

final class DocumentController
{
    public function __construct(
        private readonly CommandBus $bus,
        private readonly CurrentPrincipal $principal,
        private readonly DocumentQueries $queries,
    ) {}

    public function index(string $id): JsonResponse
    {
        return ApiResponse::json($this->queries->forApplication($id, $this->principal->require()));
    }

    public function store(Request $request, string $id): JsonResponse
    {
        $file = $request->file('file');
        if (! $file instanceof UploadedFile || ! $file->isValid()) {
            throw ValidationFailed::with(['file' => 'A file is required.']);
        }

        return ApiResponse::resource($this->bus->dispatch(new UploadDocument(
            applicationId: $id,
            tempPath: (string) $file->getRealPath(),
            originalName: $file->getClientOriginalName(),
            documentType: (string) $request->input('document_type', ''),
            checklistItemId: self::str($request->input('checklist_item_id')),
            partyId: self::str($request->input('party_id')),
            title: self::str($request->input('title')),
        ), $this->principal->require()), 201);
    }

    public function show(string $id): JsonResponse
    {
        return ApiResponse::json($this->queries->document($id, $this->principal->require()));
    }

    public function content(string $id, string $versionId): Response
    {
        $c = $this->queries->content($id, $versionId, $this->principal->require());

        return new Response($c['bytes'], 200, [
            // The sniffed type recorded at upload; nosniff stops the browser second-guessing it.
            'Content-Type' => $c['mime_type'],
            'Content-Disposition' => 'attachment; filename="'.addcslashes($c['filename'], '"\\').'"',
            'X-Content-Type-Options' => 'nosniff',
            'Digest' => 'sha-256='.base64_encode((string) hex2bin($c['sha256'])),
            'Cache-Control' => 'no-store',
        ]);
    }

    public function checklist(string $id): JsonResponse
    {
        return ApiResponse::json($this->queries->checklist($id, $this->principal->require()));
    }

    public function actOnItem(Request $request, string $id, string $action): JsonResponse
    {
        $principal = $this->principal->require();
        if ($action === 'waive') {
            return ApiResponse::changeRequest($this->bus->dispatch(new RequestChecklistWaiver($id, self::str($request->input('reason'))), $principal));
        }

        return ApiResponse::resource($this->bus->dispatch(new ReviewChecklistItem($id, $action, self::str($request->input('reason')), self::str($request->input('note')), self::str($request->input('valid_until'))), $principal));
    }

    private static function str(mixed $v): ?string
    {
        return is_string($v) && trim($v) !== '' ? trim($v) : null;
    }
}

<?php

declare(strict_types=1);

namespace Fundly\Modules\Offer\Http\Controllers;

use Fundly\Modules\Offer\Application\Commands\AcceptOffer;
use Fundly\Modules\Offer\Application\Commands\DeclineOffer;
use Fundly\Modules\Offer\Application\Commands\DeliverOffer;
use Fundly\Modules\Offer\Application\Commands\IssueOffer;
use Fundly\Modules\Offer\Application\Commands\RecordSignature;
use Fundly\Modules\Offer\Application\Commands\ReissueOffer;
use Fundly\Modules\Offer\Application\OfferQueries;
use Fundly\Shared\Bus\CommandBus;
use Fundly\Shared\Http\ApiResponse;
use Fundly\Shared\Security\CurrentPrincipal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

final class OfferController
{
    public function __construct(private readonly CommandBus $bus, private readonly CurrentPrincipal $principal, private readonly OfferQueries $queries) {}

    public function forApplication(string $id): JsonResponse
    {
        return ApiResponse::json($this->queries->forApplication($id, $this->principal->require()));
    }

    public function issue(string $id): JsonResponse
    {
        return ApiResponse::resource($this->bus->dispatch(new IssueOffer($id), $this->principal->require()), 201);
    }

    public function show(string $id): JsonResponse
    {
        return ApiResponse::json($this->queries->show($id, $this->principal->require()));
    }

    public function reissue(Request $request, string $id): JsonResponse
    {
        return ApiResponse::resource($this->bus->dispatch(new ReissueOffer($id, self::str($request->input('reason'))), $this->principal->require()), 201);
    }

    public function deliver(Request $request, string $id): JsonResponse
    {
        return ApiResponse::resource($this->bus->dispatch(new DeliverOffer($id, (string) $request->input('channel', ''), self::str($request->input('party_id'))), $this->principal->require()), 201);
    }

    public function sign(Request $request, string $id, string $signatoryId): JsonResponse
    {
        return ApiResponse::resource($this->bus->dispatch(new RecordSignature(
            $id, $signatoryId, self::str($request->input('signed_at')), self::str($request->input('witness_name')), self::str($request->input('place')),
        ), $this->principal->require()));
    }

    public function accept(Request $request, string $id): JsonResponse
    {
        $file = $request->file('file');
        $ok = $file instanceof UploadedFile && $file->isValid();

        return ApiResponse::resource($this->bus->dispatch(new AcceptOffer($id, $ok ? (string) $file->getRealPath() : null, $ok ? $file->getClientOriginalName() : 'agreement.pdf'), $this->principal->require()));
    }

    public function decline(Request $request, string $id): JsonResponse
    {
        return ApiResponse::resource($this->bus->dispatch(new DeclineOffer($id, (string) $request->input('outcome', ''), (string) $request->input('reason_code', ''), self::str($request->input('note'))), $this->principal->require()));
    }

    private static function str(mixed $v): ?string
    {
        return is_string($v) && trim($v) !== '' ? trim($v) : null;
    }
}

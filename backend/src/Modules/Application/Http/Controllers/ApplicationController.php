<?php

declare(strict_types=1);

namespace Fundly\Modules\Application\Http\Controllers;

use Fundly\Modules\Application\Application\ApplicationQueries;
use Fundly\Modules\Application\Application\Commands\ActOnApplication;
use Fundly\Modules\Application\Application\Commands\AmendApplication;
use Fundly\Modules\Application\Application\Commands\ChangeApplicant;
use Fundly\Modules\Application\Application\Commands\CreateApplication;
use Fundly\Modules\Application\Domain\ApplicationAction;
use Fundly\Shared\Bus\CommandBus;
use Fundly\Shared\Exceptions\NotFound;
use Fundly\Shared\Http\ApiResponse;
use Fundly\Shared\Security\CurrentPrincipal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ApplicationController
{
    public function __construct(
        private readonly CommandBus $bus,
        private readonly CurrentPrincipal $principal,
        private readonly ApplicationQueries $queries,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::json($this->queries->list($request, $this->principal->require()));
    }

    public function stats(): JsonResponse
    {
        return ApiResponse::json($this->queries->stats($this->principal->require()));
    }

    public function show(string $id): JsonResponse
    {
        return ApiResponse::resource($this->queries->show($id, $this->principal->require()));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->input('data', []);
        $tenor = $request->input('tenor_months');

        return ApiResponse::resource($this->bus->dispatch(new CreateApplication(
            legalEntityId: (string) $request->input('legal_entity_id', ''),
            orgUnitId: (string) $request->input('org_unit_id', ''),
            productKey: (string) $request->input('product_key', ''),
            primaryPartyId: (string) $request->input('primary_party_id', ''),
            channel: (string) $request->input('channel', 'staff'),
            requestedAmount: self::str($request->input('requested_amount')),
            tenorMonths: is_int($tenor) ? $tenor : null,
            purpose: self::str($request->input('purpose')),
            repaymentFrequency: self::str($request->input('repayment_frequency')),
            data: is_array($data) ? self::map($data) : [],
        ), $this->principal->require()), 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $changes = [];
        foreach (['requested_amount', 'tenor_months', 'purpose', 'repayment_frequency'] as $f) {
            if ($request->exists($f)) {
                $changes[$f] = $request->input($f);
            }
        }
        if ($request->exists('data') && is_array($request->input('data'))) {
            $changes['data'] = self::map((array) $request->input('data'));
        }

        return ApiResponse::resource($this->bus->dispatch(new AmendApplication(
            applicationId: $id,
            ifMatch: self::header($request),
            changes: $changes,
            source: (string) $request->input('source', 'staff'),
            sourceRef: self::str($request->input('source_ref')),
        ), $this->principal->require()));
    }

    public function addApplicant(Request $request, string $id): JsonResponse
    {
        return ApiResponse::resource($this->bus->dispatch(new ChangeApplicant($id, self::header($request), 'add', (string) $request->input('party_id', ''), self::str($request->input('role'))), $this->principal->require()), 201);
    }

    public function removeApplicant(Request $request, string $id, string $partyId): JsonResponse
    {
        return ApiResponse::resource($this->bus->dispatch(new ChangeApplicant($id, self::header($request), 'remove', $partyId, null), $this->principal->require()));
    }

    public function act(Request $request, string $id, string $action): JsonResponse
    {
        $verb = ApplicationAction::tryFrom($action) ?? throw new NotFound("Unknown action {$action}.");

        return ApiResponse::resource($this->bus->dispatch(new ActOnApplication(
            applicationId: $id,
            ifMatch: self::header($request),
            verb: $verb,
            reasonCode: self::str($request->input('reason_code')),
            reasonText: self::str($request->input('reason_text')),
            returnTo: self::str($request->input('return_to')),
        ), $this->principal->require()));
    }

    public function timeline(string $id): JsonResponse
    {
        return ApiResponse::json($this->queries->timeline($id, $this->principal->require()));
    }

    public function asAt(Request $request, string $id): JsonResponse
    {
        return ApiResponse::json($this->queries->asAt($id, (string) $request->query('t', ''), $this->principal->require()));
    }

    private static function header(Request $request): ?string
    {
        $h = $request->headers->get('If-Match');

        return is_string($h) ? $h : null;
    }

    private static function str(mixed $v): ?string
    {
        return is_string($v) && trim($v) !== '' ? trim($v) : null;
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<string, mixed>
     */
    private static function map(array $data): array
    {
        $out = [];
        foreach ($data as $k => $v) {
            if (is_string($k) && preg_match('/^[a-z][a-z0-9_]{0,63}$/', $k) === 1 && (is_scalar($v) || $v === null)) {
                $out[$k] = $v;
            }
        }

        return $out;
    }
}

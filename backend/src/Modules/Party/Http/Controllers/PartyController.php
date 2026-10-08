<?php

declare(strict_types=1);

namespace Fundly\Modules\Party\Http\Controllers;

use Fundly\Modules\Party\Application\Commands\AddPartyRelationship;
use Fundly\Modules\Party\Application\Commands\CreateParty;
use Fundly\Modules\Party\Application\Commands\RecordConsent;
use Fundly\Modules\Party\Application\Commands\VerifyPartyIdentity;
use Fundly\Modules\Party\Application\PartyQueries;
use Fundly\Modules\Party\Domain\PhoneNumber;
use Fundly\Shared\Bus\CommandBus;
use Fundly\Shared\Http\ApiResponse;
use Fundly\Shared\Security\CurrentPrincipal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PartyController
{
    public function __construct(
        private readonly CommandBus $bus,
        private readonly CurrentPrincipal $principal,
        private readonly PartyQueries $queries,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::json($this->queries->list($request, $this->principal->require()));
    }

    public function show(string $id): JsonResponse
    {
        return ApiResponse::resource($this->queries->show($id, $this->principal->require()));
    }

    public function store(Request $request): JsonResponse
    {
        $address = $request->input('address');
        $identities = [];
        $raw = $request->input('identities', []);
        foreach (is_array($raw) ? $raw : [] as $i) {
            if (is_array($i)) {
                $identities[] = ['type' => self::str($i['type'] ?? null) ?? '', 'value' => self::str($i['value'] ?? null) ?? ''];
            }
        }

        return ApiResponse::resource($this->bus->dispatch(new CreateParty(
            type: (string) $request->input('type', ''),
            orgUnitId: self::str($request->input('org_unit_id')),
            firstName: self::str($request->input('first_name')),
            middleName: self::str($request->input('middle_name')),
            lastName: self::str($request->input('last_name')),
            gender: self::str($request->input('gender')),
            dateOfBirth: self::str($request->input('date_of_birth')),
            nationality: self::str($request->input('nationality')),
            companyName: self::str($request->input('company_name')),
            registrationNumber: self::str($request->input('registration_number')),
            incorporationDate: self::str($request->input('incorporation_date')),
            sector: self::str($request->input('sector')),
            phone: self::str($request->input('phone')),
            email: self::str($request->input('email')),
            address: is_array($address) ? array_map(static fn ($v): string => is_scalar($v) ? (string) $v : '', array_intersect_key($address, array_flip(['line1', 'city', 'state', 'country']))) : null,
            tin: self::str($request->input('tin')),
            identities: $identities,
            cbaCustomerId: self::str($request->input('cba_customer_id')),
        ), $this->principal->require()), 201);
    }

    public function relationships(string $id): JsonResponse
    {
        return ApiResponse::json($this->queries->relationships($id, $this->principal->require()));
    }

    public function addRelationship(Request $request, string $id): JsonResponse
    {
        return ApiResponse::resource($this->bus->dispatch(new AddPartyRelationship(
            partyId: $id,
            relatedPartyId: (string) $request->input('related_party_id', ''),
            role: (string) $request->input('role', ''),
            ownershipPercent: self::str($request->input('ownership_percent')),
            notes: self::str($request->input('notes')),
        ), $this->principal->require()), 201);
    }

    public function match(Request $request): JsonResponse
    {
        $identities = [];
        $raw = $request->input('identities', []);
        foreach (is_array($raw) ? $raw : [] as $i) {
            if (is_array($i) && is_string($i['type'] ?? null) && is_string($i['value'] ?? null)) {
                $identities[] = ['type' => $i['type'], 'value' => $i['value']];
            }
        }

        return ApiResponse::json($this->queries->match([
            'name' => self::str($request->input('name')),
            'phone' => ($p = self::str($request->input('phone'))) === null ? null : PhoneNumber::normalise($p),
            'email' => ($e = self::str($request->input('email'))) === null ? null : mb_strtolower(trim($e)),
            'tin' => self::str($request->input('tin')),
            'registration_number' => self::str($request->input('registration_number')),
            'identities' => $identities,
        ]));
    }

    public function consents(string $id): JsonResponse
    {
        return ApiResponse::json($this->queries->consentView($id, $this->principal->require()));
    }

    public function recordConsent(Request $request, string $id): JsonResponse
    {
        return ApiResponse::resource($this->bus->dispatch(new RecordConsent(
            partyId: $id,
            purpose: (string) $request->input('purpose', ''),
            consentAction: (string) $request->input('action', ''),
            channel: (string) $request->input('channel', ''),
            termsVersion: (string) $request->input('terms_version', ''),
            evidenceRef: self::str($request->input('evidence_ref')),
            applicationId: self::str($request->input('application_id')),
        ), $this->principal->require()), 201);
    }

    public function verifyIdentity(string $id, string $type): JsonResponse
    {
        return ApiResponse::resource($this->bus->dispatch(new VerifyPartyIdentity($id, $type), $this->principal->require()));
    }

    private static function str(mixed $v): ?string
    {
        return is_string($v) && trim($v) !== '' ? trim($v) : null;
    }
}

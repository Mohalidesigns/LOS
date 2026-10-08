<?php

declare(strict_types=1);

namespace Fundly\Modules\Party\Application\Commands;

use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Bus\ValidatesInput;
use Fundly\Shared\Security\ResourceRef;

/**
 * Create an individual or limited-company party (FR-CUS-001). Sensitive
 * fields are encrypted at rest; intake dedupe candidates are returned with
 * the result (FR-CHN-007, policy: flag).
 */
#[HandledBy(CreatePartyHandler::class)]
final readonly class CreateParty implements Command, ValidatesInput
{
    /**
     * @param  array{line1?: string, city?: string, state?: string, country?: string}|null  $address
     * @param  list<array{type: string, value: string}>  $identities
     */
    public function __construct(
        public string $type,
        public ?string $orgUnitId,
        public ?string $firstName,
        public ?string $middleName,
        public ?string $lastName,
        public ?string $gender,
        public ?string $dateOfBirth,
        public ?string $nationality,
        public ?string $companyName,
        public ?string $registrationNumber,
        public ?string $incorporationDate,
        public ?string $sector,
        public ?string $phone,
        public ?string $email,
        public ?array $address,
        public ?string $tin,
        public array $identities,
        public ?string $cbaCustomerId,
    ) {}

    public function action(): string
    {
        return 'party.created';
    }

    public function permission(): string
    {
        return 'party:manage';
    }

    public function resource(): ?ResourceRef
    {
        return null;
    }

    public function data(): array
    {
        return [
            'type' => $this->type, 'org_unit_id' => $this->orgUnitId, 'first_name' => $this->firstName, 'middle_name' => $this->middleName,
            'last_name' => $this->lastName, 'gender' => $this->gender, 'date_of_birth' => $this->dateOfBirth, 'nationality' => $this->nationality,
            'company_name' => $this->companyName, 'registration_number' => $this->registrationNumber, 'incorporation_date' => $this->incorporationDate,
            'sector' => $this->sector, 'phone' => $this->phone, 'email' => $this->email, 'address' => $this->address, 'tin' => $this->tin,
            'identities' => $this->identities, 'cba_customer_id' => $this->cbaCustomerId,
        ];
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:individual,limited_company'],
            'org_unit_id' => ['nullable', 'uuid'],
            'first_name' => ['required_if:type,individual', 'nullable', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required_if:type,individual', 'nullable', 'string', 'max:100'],
            'gender' => ['nullable', 'in:female,male,other,undisclosed'],
            'date_of_birth' => ['required_if:type,individual', 'nullable', 'date_format:Y-m-d', 'before:today'],
            'nationality' => ['nullable', 'regex:/^[A-Z]{2}$/'],
            'company_name' => ['required_if:type,limited_company', 'nullable', 'string', 'max:300'],
            'registration_number' => ['required_if:type,limited_company', 'nullable', 'string', 'regex:/^(RC|BN|IT)?\s?\d{1,9}$/i'],
            'incorporation_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'sector' => ['nullable', 'string', 'max:64'],
            'phone' => ['nullable', 'string', 'regex:/^\+?[0-9]{10,15}$/'],
            'email' => ['nullable', 'email:rfc', 'max:254'],
            'address' => ['nullable', 'array'],
            'address.line1' => ['nullable', 'string', 'max:300'],
            'address.city' => ['nullable', 'string', 'max:100'],
            'address.state' => ['nullable', 'string', 'max:100'],
            'address.country' => ['nullable', 'regex:/^[A-Z]{2}$/'],
            'tin' => ['nullable', 'string', 'regex:/^[0-9-]{8,15}$/'],
            'identities' => ['array', 'max:5'],
            'identities.*.type' => ['required', 'distinct', 'in:bvn,nin,passport,drivers_licence,voters_card'],
            'identities.*.value' => ['required', 'string', 'max:32'],
            'cba_customer_id' => ['nullable', 'string', 'max:64'],
        ];
    }
}

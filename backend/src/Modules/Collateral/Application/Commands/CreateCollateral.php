<?php

declare(strict_types=1);

namespace Fundly\Modules\Collateral\Application\Commands;

use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Bus\ValidatesInput;
use Fundly\Shared\Security\ResourceRef;

/** Capture an item of a configured type (FR-COL-001); its perfection checklist is created and its lending value allocated to the application. */
#[HandledBy(CreateCollateralHandler::class)]
final readonly class CreateCollateral implements Command, ValidatesInput
{
    public function __construct(public string $applicationId, public ?string $typeCode, public ?string $description, public ?string $marketValue, public ?string $ownerPartyId = null, public ?string $ownerName = null, public ?string $stateCode = null) {}

    public function action(): string
    {
        return 'collateral.created';
    }

    public function permission(): string
    {
        return 'collateral:manage';
    }

    public function resource(): ResourceRef
    {
        return new ResourceRef('application', $this->applicationId);
    }

    public function data(): array
    {
        return ['type_code' => $this->typeCode, 'description' => $this->description, 'market_value' => $this->marketValue, 'owner_party_id' => $this->ownerPartyId, 'owner_name' => $this->ownerName, 'state_code' => $this->stateCode];
    }

    public function rules(): array
    {
        return ['type_code' => ['required', 'string', 'regex:/^[a-z][a-z0-9_]{1,47}$/'], 'description' => ['required', 'string', 'min:3', 'max:500'], 'market_value' => ['required', 'string', 'regex:/^\\d{1,16}(\\.\\d{1,4})?$/'], 'owner_party_id' => ['nullable', 'uuid'], 'owner_name' => ['nullable', 'string', 'max:200'], 'state_code' => ['nullable', 'string', 'regex:/^[A-Z]{2}$/']];
    }
}

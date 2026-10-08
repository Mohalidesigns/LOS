<?php

declare(strict_types=1);

namespace Fundly\Modules\Application\Application\Commands;

use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Bus\ValidatesInput;
use Fundly\Shared\Security\ResourceRef;

/**
 * Open a draft application (FR-APP-001, FR-CHN-001/002/003, FR-PRD-006): pins
 * the active product version, assigns the human reference and records
 * channel attribution and provenance. Scope is checked against the target
 * branch.
 *
 * @phpstan-type Data array<string, mixed>
 */
#[HandledBy(CreateApplicationHandler::class)]
final readonly class CreateApplication implements Command, ValidatesInput
{
    /** @param array<string, mixed> $data */
    public function __construct(
        public string $legalEntityId,
        public string $orgUnitId,
        public string $productKey,
        public string $primaryPartyId,
        public string $channel,
        public ?string $requestedAmount,
        public ?int $tenorMonths,
        public ?string $purpose,
        public ?string $repaymentFrequency,
        public array $data,
    ) {}

    public function action(): string
    {
        return 'application.created';
    }

    public function permission(): string
    {
        return 'application:originate';
    }

    public function resource(): ResourceRef
    {
        return new ResourceRef('org_unit', $this->orgUnitId);
    }

    public function data(): array
    {
        return [
            'legal_entity_id' => $this->legalEntityId, 'org_unit_id' => $this->orgUnitId, 'product_key' => $this->productKey,
            'primary_party_id' => $this->primaryPartyId, 'channel' => $this->channel, 'requested_amount' => $this->requestedAmount,
            'tenor_months' => $this->tenorMonths, 'purpose' => $this->purpose, 'repayment_frequency' => $this->repaymentFrequency, 'data' => $this->data,
        ];
    }

    public function rules(): array
    {
        return [
            'legal_entity_id' => ['required', 'uuid'],
            'org_unit_id' => ['required', 'uuid'],
            'product_key' => ['required', 'string', 'max:96'],
            'primary_party_id' => ['required', 'uuid'],
            'channel' => ['required', 'in:staff,api'],
            'requested_amount' => ['nullable', 'regex:/^\d{1,16}(\.\d{1,4})?$/'],
            'tenor_months' => ['nullable', 'integer', 'min:1', 'max:600'],
            'purpose' => ['nullable', 'string', 'max:500'],
            'repayment_frequency' => ['nullable', 'in:weekly,monthly,quarterly,bullet'],
            'data' => ['array'],
        ];
    }
}

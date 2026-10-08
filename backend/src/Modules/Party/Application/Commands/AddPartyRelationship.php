<?php

declare(strict_types=1);

namespace Fundly\Modules\Party\Application\Commands;

use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Bus\ValidatesInput;
use Fundly\Shared\Security\ResourceRef;

/** Link a director, shareholder, beneficial owner or signatory to a company (FR-CUS-006). */
#[HandledBy(AddPartyRelationshipHandler::class)]
final readonly class AddPartyRelationship implements Command, ValidatesInput
{
    public function __construct(
        public string $partyId,
        public string $relatedPartyId,
        public string $role,
        public ?string $ownershipPercent,
        public ?string $notes,
    ) {}

    public function action(): string
    {
        return 'party.relationship.added';
    }

    public function permission(): string
    {
        return 'party:manage';
    }

    public function resource(): ResourceRef
    {
        return new ResourceRef('party', $this->partyId);
    }

    public function data(): array
    {
        return ['related_party_id' => $this->relatedPartyId, 'role' => $this->role, 'ownership_percent' => $this->ownershipPercent, 'notes' => $this->notes];
    }

    public function rules(): array
    {
        return [
            'related_party_id' => ['required', 'uuid'],
            'role' => ['required', 'in:director,shareholder,beneficial_owner,signatory,company_secretary'],
            'ownership_percent' => ['nullable', 'regex:/^\d{1,3}(\.\d{1,4})?$/'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}

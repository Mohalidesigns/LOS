<?php

declare(strict_types=1);

namespace Fundly\Modules\Party\Application\Commands;

use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Bus\ValidatesInput;
use Fundly\Shared\Security\ResourceRef;

/** Verify a recorded BVN / NIN through the IdentityVerificationPort (FR-CUS-002/003). */
#[HandledBy(VerifyPartyIdentityHandler::class)]
final readonly class VerifyPartyIdentity implements Command, ValidatesInput
{
    public function __construct(public string $partyId, public string $identityType) {}

    public function action(): string
    {
        return 'party.identity.verified';
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
        return ['identity_type' => $this->identityType];
    }

    public function rules(): array
    {
        return ['identity_type' => ['required', 'in:bvn,nin,passport,drivers_licence,voters_card']];
    }
}

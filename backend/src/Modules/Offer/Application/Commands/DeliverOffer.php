<?php

declare(strict_types=1);

namespace Fundly\Modules\Offer\Application\Commands;

use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Bus\ValidatesInput;
use Fundly\Shared\Security\ResourceRef;

/** Send the offer by email or record a printed branch copy (FR-OFR-004). */
#[HandledBy(DeliverOfferHandler::class)]
final readonly class DeliverOffer implements Command, ValidatesInput
{
    public function __construct(public string $offerId, public string $channel, public ?string $partyId) {}

    public function action(): string
    {
        return 'offer.delivered';
    }

    public function permission(): string
    {
        return 'offer:prepare';
    }

    public function resource(): ResourceRef
    {
        return new ResourceRef('offer', $this->offerId);
    }

    public function data(): array
    {
        return ['channel' => $this->channel, 'party_id' => $this->partyId];
    }

    public function rules(): array
    {
        return ['channel' => ['required', 'in:email,print'], 'party_id' => ['nullable', 'uuid']];
    }
}

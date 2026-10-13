<?php

declare(strict_types=1);

namespace Fundly\Modules\Offer\Application\Commands;

use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Security\ResourceRef;

/** Accept a fully signed offer: upload the executed agreement, which is stored immutably with its hash (FR-OFR-005/010). */
#[HandledBy(AcceptOfferHandler::class)]
final readonly class AcceptOffer implements Command
{
    public function __construct(public string $offerId, public ?string $tempPath, public string $originalName) {}

    public function action(): string
    {
        return 'offer.accepted';
    }

    public function permission(): string
    {
        return 'offer:prepare';
    }

    public function resource(): ResourceRef
    {
        return new ResourceRef('offer', $this->offerId);
    }
}

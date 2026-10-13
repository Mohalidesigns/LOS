<?php

declare(strict_types=1);

namespace Fundly\Modules\Offer\Application\Commands;

use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Security\ResourceRef;

/** An issued offer past its validity expires (FR-OFR-007). Platform-only. */
#[HandledBy(ExpireOfferHandler::class)]
final readonly class ExpireOffer implements Command
{
    public function __construct(public string $offerId) {}

    public function action(): string
    {
        return 'offer.expired';
    }

    public function permission(): ?string
    {
        return null;
    }

    public function resource(): ResourceRef
    {
        return new ResourceRef('offer', $this->offerId);
    }
}

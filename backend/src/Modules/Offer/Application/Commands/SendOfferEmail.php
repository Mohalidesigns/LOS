<?php

declare(strict_types=1);

namespace Fundly\Modules\Offer\Application\Commands;

use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Security\ResourceRef;

/** Outbox step: email the offer letter for a queued delivery. Platform-only. */
#[HandledBy(SendOfferEmailHandler::class)]
final readonly class SendOfferEmail implements Command
{
    public function __construct(public string $deliveryId) {}

    public function action(): string
    {
        return 'offer.email_sent';
    }

    public function permission(): ?string
    {
        return null;
    }

    public function resource(): ResourceRef
    {
        return new ResourceRef('offer_delivery', $this->deliveryId);
    }
}

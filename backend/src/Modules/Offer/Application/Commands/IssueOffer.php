<?php

declare(strict_types=1);

namespace Fundly\Modules\Offer\Application\Commands;

use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Security\ResourceRef;

/** Issue the offer on an approved or counter-offered application (FR-OFR-001..003). */
#[HandledBy(IssueOfferHandler::class)]
final readonly class IssueOffer implements Command
{
    public function __construct(public string $applicationId) {}

    public function action(): string
    {
        return 'offer.issued';
    }

    public function permission(): string
    {
        return 'offer:prepare';
    }

    public function resource(): ResourceRef
    {
        return new ResourceRef('application', $this->applicationId);
    }
}

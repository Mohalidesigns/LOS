<?php

declare(strict_types=1);

namespace Fundly\Modules\Offer\Contracts;

use DateTimeImmutable;

/** An issued offer that lapses soon, for reminders (FR-OFR-007). */
final readonly class ExpiringOffer
{
    public function __construct(public string $offerId, public string $applicationId, public int $sequence, public DateTimeImmutable $validUntil) {}
}

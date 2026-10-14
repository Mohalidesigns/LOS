<?php

declare(strict_types=1);

namespace Fundly\Modules\Offer\Contracts;

use DateTimeImmutable;

interface OfferRegister
{
    public function accepted(string $applicationId): ?AcceptedOffer;

    /** @return list<ExpiringOffer> issued offers whose validity ends within the window */
    public function expiring(DateTimeImmutable $from, DateTimeImmutable $to): array;
}

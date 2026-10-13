<?php

declare(strict_types=1);

namespace Fundly\Modules\Offer\Contracts;

interface OfferRegister
{
    public function accepted(string $applicationId): ?AcceptedOffer;
}

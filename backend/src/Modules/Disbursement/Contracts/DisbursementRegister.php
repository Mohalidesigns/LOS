<?php

declare(strict_types=1);

namespace Fundly\Modules\Disbursement\Contracts;

interface DisbursementRegister
{
    public function booked(string $applicationId): ?BookedFacility;
}

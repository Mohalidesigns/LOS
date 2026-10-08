<?php

declare(strict_types=1);

namespace Fundly\Modules\Party\Contracts;

/** Whether a party currently consents to a purpose (latest grant not withdrawn). */
interface ConsentRegistry
{
    public const DATA_PROCESSING = 'data_processing';

    public const CREDIT_BUREAU = 'credit_bureau';

    public function active(string $partyId, string $purpose): bool;
}

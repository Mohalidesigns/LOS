<?php

declare(strict_types=1);

namespace Fundly\Integration\Ports\CreditBureau;

use Fundly\Integration\Runtime\OperationPolicy;

final class CreditBureauOperations
{
    /** An enquiry is a read; a duplicate enquiry costs money but changes nothing, so one inline retry. */
    public static function policy(): OperationPolicy
    {
        return new OperationPolicy(stateChanging: false, timeoutMs: 15000, inlineRetries: 1);
    }
}

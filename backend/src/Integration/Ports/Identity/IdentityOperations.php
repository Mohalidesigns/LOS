<?php

declare(strict_types=1);

namespace Fundly\Integration\Ports\Identity;

use Fundly\Integration\Runtime\OperationPolicy;

final class IdentityOperations
{
    /** Lookups are reads: inline retries with backoff are safe. */
    public static function policy(): OperationPolicy
    {
        return new OperationPolicy(stateChanging: false, timeoutMs: 5000, inlineRetries: 2);
    }
}

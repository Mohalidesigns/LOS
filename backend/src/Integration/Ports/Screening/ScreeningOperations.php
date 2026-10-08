<?php

declare(strict_types=1);

namespace Fundly\Integration\Ports\Screening;

use Fundly\Integration\Runtime\OperationPolicy;

final class ScreeningOperations
{
    public static function policy(): OperationPolicy
    {
        return new OperationPolicy(stateChanging: false, timeoutMs: 10000, inlineRetries: 2);
    }
}

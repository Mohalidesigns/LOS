<?php

declare(strict_types=1);

namespace Fundly\Integration\Ports\PdfRender;

use Fundly\Integration\Runtime\OperationPolicy;

final class PdfRenderOperations
{
    public static function policy(): OperationPolicy
    {
        return new OperationPolicy(stateChanging: false, timeoutMs: 30000, inlineRetries: 1);
    }
}

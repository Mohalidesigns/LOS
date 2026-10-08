<?php

declare(strict_types=1);

namespace Fundly\Integration\Runtime;

final readonly class OperationPolicy
{
    public function __construct(public bool $stateChanging, public int $timeoutMs, public int $inlineRetries) {}
}

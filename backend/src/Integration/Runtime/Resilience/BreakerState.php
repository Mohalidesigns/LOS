<?php

declare(strict_types=1);

namespace Fundly\Integration\Runtime\Resilience;

final readonly class BreakerState
{
    public const CLOSED = 'closed';

    public const OPEN = 'open';

    public const HALF_OPEN = 'half_open';

    public function __construct(public string $state, public int $consecutiveFailures, public ?int $openedAt)
    {
    }

    public static function closed(): self
    {
        return new self(self::CLOSED, 0, null);
    }
}

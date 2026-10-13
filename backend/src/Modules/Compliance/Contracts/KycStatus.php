<?php

declare(strict_types=1);

namespace Fundly\Modules\Compliance\Contracts;

/** The CDD gate's current verdict for an application. */
final readonly class KycStatus
{
    /** @param list<string> $unmet human-readable conditions not met */
    public function __construct(public string $outcome, public string $risk, public array $unmet) {}

    public function isClear(): bool
    {
        return $this->outcome === 'clear';
    }
}

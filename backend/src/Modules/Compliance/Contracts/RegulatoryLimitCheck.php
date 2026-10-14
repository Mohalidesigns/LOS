<?php

declare(strict_types=1);

namespace Fundly\Modules\Compliance\Contracts;

/** The recorded result of a regulatory limit check (FR-CMP-023). */
final readonly class RegulatoryLimitCheck
{
    /** @param 'pass'|'breach'|'unavailable'|'not_applicable' $outcome */
    public function __construct(public string $checkId, public string $outcome, public string $detail) {}

    /** A breach, or exposure that could not be read: either stops approval and release. */
    public function blocks(): bool
    {
        return $this->outcome === 'breach' || $this->outcome === 'unavailable';
    }
}

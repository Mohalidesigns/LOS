<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Domain;

/** Session controls (FR-SEC-015). */
final readonly class SessionPolicy
{
    public function __construct(public int $idleMinutes, public int $absoluteMinutes, public int $maxConcurrent) {}

    /** Returns the reason the session is no longer valid, or null. */
    public function violation(int $authenticatedAt, int $lastActivityAt, int $now): ?string
    {
        if ($now - $authenticatedAt > $this->absoluteMinutes * 60) {
            return 'absolute_timeout';
        }
        if ($now - $lastActivityAt > $this->idleMinutes * 60) {
            return 'idle_timeout';
        }

        return null;
    }
}

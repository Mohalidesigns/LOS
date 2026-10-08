<?php

declare(strict_types=1);

namespace Fundly\Shared\Audit;

interface AuditTrail
{
    /** Appends an event to the current tenant's chain; returns the event id. */
    public function record(AuditEntry $entry, ?Actor $actor = null): string;
}

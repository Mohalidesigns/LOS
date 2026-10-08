<?php

declare(strict_types=1);

namespace Fundly\Shared\Outbox;

use Fundly\Shared\Bus\OutboxIntent;

interface Outbox
{
    /** Persists an outbox message in the *current* transaction; returns its id. */
    public function add(OutboxIntent $intent): string;
}

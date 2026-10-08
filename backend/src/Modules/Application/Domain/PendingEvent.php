<?php

declare(strict_types=1);

namespace Fundly\Modules\Application\Domain;

/** An event raised by the aggregate and not yet appended to the store. */
final readonly class PendingEvent
{
    /** @param array<string, mixed> $payload */
    public function __construct(public string $type, public array $payload, public int $version) {}
}

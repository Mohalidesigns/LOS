<?php

declare(strict_types=1);

namespace Fundly\Shared\Bus;

/** Something that happened in a module; cross-module side effects flow through these (TRD §2.1). */
interface DomainEvent
{
    public function name(): string;

    /** @return array<string, mixed> */
    public function payload(): array;
}

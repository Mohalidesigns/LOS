<?php

declare(strict_types=1);

namespace Fundly\Shared\Bus;

use Attribute;

/** Binds a command class to its handler. */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class HandledBy
{
    /** @param class-string<CommandHandler> $handler */
    public function __construct(public string $handler)
    {
    }
}

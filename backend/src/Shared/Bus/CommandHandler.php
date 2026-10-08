<?php

declare(strict_types=1);

namespace Fundly\Shared\Bus;

/**
 * Handles exactly one command type. Handlers never open transactions, write
 * audit rows or outbox messages directly: they record intent on the context
 * and the bus middleware persists it in the right order.
 */
interface CommandHandler
{
    public function handle(Command $command, CommandContext $context): mixed;
}

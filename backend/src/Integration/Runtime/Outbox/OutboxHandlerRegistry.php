<?php

declare(strict_types=1);

namespace Fundly\Integration\Runtime\Outbox;

use Fundly\Integration\Runtime\Errors\NonRetryableError;

final class OutboxHandlerRegistry
{
    /** @var array<string, OutboxHandler> */
    private array $handlers = [];

    public function register(OutboxHandler $handler): void
    {
        $this->handlers[$handler->topic()] = $handler;
    }

    public function for(string $topic): OutboxHandler
    {
        return $this->handlers[$topic] ?? throw new NonRetryableError('INTEGRATION.OUTBOX.NO_HANDLER', "No outbox handler for topic {$topic}.");
    }
}

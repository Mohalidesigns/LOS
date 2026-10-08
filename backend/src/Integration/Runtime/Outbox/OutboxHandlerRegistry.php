<?php

declare(strict_types=1);

namespace Fundly\Integration\Runtime\Outbox;

use Fundly\Integration\Runtime\Errors\NonRetryableError;
use Illuminate\Contracts\Container\Container;

/** Topic → handler class; handlers are resolved per message so scoped collaborators are fresh. */
final class OutboxHandlerRegistry
{
    /** @var array<string, class-string<OutboxHandler>> */
    private array $handlers = [];

    public function __construct(private readonly Container $container)
    {
    }

    /** @param class-string<OutboxHandler> $class */
    public function register(string $topic, string $class): void
    {
        $this->handlers[$topic] = $class;
    }

    public function for(string $topic): OutboxHandler
    {
        $class = $this->handlers[$topic] ?? throw new NonRetryableError('INTEGRATION.OUTBOX.NO_HANDLER', "No outbox handler for topic {$topic}.");
        $handler = $this->container->make($class);
        assert($handler instanceof OutboxHandler);

        return $handler;
    }
}

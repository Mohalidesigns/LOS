<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Application\ChangeRequests;

use Fundly\Modules\Access\Contracts\ChangeAction;
use Fundly\Shared\Exceptions\NotFound;
use Illuminate\Contracts\Container\Container;
use LogicException;

/**
 * Maps action types to action classes. Actions are resolved from the
 * container on each use, so they always get the current request/job-scoped
 * collaborators (never instances captured at boot).
 */
final class ChangeActionRegistry
{
    /** @var array<string, class-string<ChangeAction>> */
    private array $actions = [];

    public function __construct(private readonly Container $container) {}

    /** @param class-string<ChangeAction> $class */
    public function register(string $type, string $class): void
    {
        if (isset($this->actions[$type])) {
            throw new LogicException("Change action {$type} registered twice.");
        }
        $this->actions[$type] = $class;
    }

    public function get(string $type): ChangeAction
    {
        $class = $this->actions[$type] ?? throw new NotFound("Unknown change action type {$type}.");
        $action = $this->container->make($class);
        if (! $action instanceof ChangeAction || $action->type() !== $type) {
            throw new LogicException("{$class} is not the change action for {$type}.");
        }

        return $action;
    }

    /** @return list<string> */
    public function types(): array
    {
        return array_keys($this->actions);
    }
}

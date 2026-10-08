<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Application\ChangeRequests;

use Fundly\Modules\Access\Contracts\ChangeAction;
use Fundly\Shared\Exceptions\NotFound;
use LogicException;

final class ChangeActionRegistry
{
    /** @var array<string, ChangeAction> */
    private array $actions = [];

    public function register(ChangeAction $action): void
    {
        if (isset($this->actions[$action->type()])) {
            throw new LogicException("Change action {$action->type()} registered twice.");
        }
        $this->actions[$action->type()] = $action;
    }

    public function get(string $type): ChangeAction
    {
        return $this->actions[$type] ?? throw new NotFound("Unknown change action type {$type}.");
    }

    /** @return list<string> */
    public function types(): array
    {
        return array_keys($this->actions);
    }
}

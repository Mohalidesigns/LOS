<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Application\Commands\Delegations;

use Fundly\Modules\Access\Domain\Permission;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Security\ResourceRef;

#[HandledBy(RevokeDelegationHandler::class)]
final readonly class RevokeDelegation implements Command
{
    public function __construct(public string $delegationId) {}

    public function action(): string
    {
        return 'access.delegation.revoked';
    }

    public function permission(): string
    {
        return Permission::DelegationCreate->value;
    }

    public function resource(): ResourceRef
    {
        return new ResourceRef('delegation', $this->delegationId);
    }
}

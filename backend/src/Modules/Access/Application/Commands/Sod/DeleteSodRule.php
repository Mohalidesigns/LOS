<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Application\Commands\Sod;

use Fundly\Modules\Access\Domain\Permission;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Security\ResourceRef;

#[HandledBy(DeleteSodRuleHandler::class)]
final readonly class DeleteSodRule implements Command
{
    public function __construct(public string $ruleId)
    {
    }

    public function action(): string
    {
        return 'access.sod_rule.deleted';
    }

    public function permission(): string
    {
        return Permission::SodRuleManage->value;
    }

    public function resource(): ResourceRef
    {
        return new ResourceRef('sod_rule', $this->ruleId);
    }
}

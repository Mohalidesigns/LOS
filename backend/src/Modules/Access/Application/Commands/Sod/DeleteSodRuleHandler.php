<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Application\Commands\Sod;

use Fundly\Modules\Access\Infrastructure\Models\SodRuleRecord;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;

final class DeleteSodRuleHandler implements CommandHandler
{
    public function handle(Command $command, CommandContext $context): null
    {
        assert($command instanceof DeleteSodRule);
        $rule = SodRuleRecord::query()->findOrFail($command->ruleId);
        $before = $rule->only(['kind', 'left_ref', 'right_ref', 'description']);
        $rule->delete();
        $context->audit(new AuditEntry(action: $command->action(), entityType: 'sod_rule', entityId: $command->ruleId, before: $before));

        return null;
    }
}

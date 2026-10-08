<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Application\Commands\Sod;

use Fundly\Modules\Access\Application\Queries\AccessQueries;
use Fundly\Modules\Access\Infrastructure\Models\Role;
use Fundly\Modules\Access\Infrastructure\Models\SodRuleRecord;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Exceptions\CodedConflict;
use Fundly\Shared\Exceptions\ValidationFailed;

final class CreateSodRuleHandler implements CommandHandler
{
    /** @return array{data: array<string, mixed>, etag?: string} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof CreateSodRule);
        if ($command->kind === 'role_pair') {
            foreach (['left' => $command->left, 'right' => $command->right] as $field => $id) {
                if (! Role::query()->whereKey($id)->exists()) {
                    throw ValidationFailed::with([$field => 'Role does not exist.']);
                }
            }
        }
        $exists = SodRuleRecord::query()->where('kind', $command->kind)
            ->where(fn ($q) => $q->where(['left_ref' => $command->left, 'right_ref' => $command->right])
                ->orWhere(fn ($q2) => $q2->where(['left_ref' => $command->right, 'right_ref' => $command->left])))
            ->exists();
        if ($exists) {
            throw new CodedConflict('sod-rule-exists', 'This SoD rule already exists.');
        }
        $rule = new SodRuleRecord;
        $rule->forceFill([
            'kind' => $command->kind,
            'left_ref' => $command->left,
            'right_ref' => $command->right,
            'description' => $command->description,
            'enabled' => true,
            'created_by' => $context->principal->id,
        ])->save();
        $context->audit(new AuditEntry(action: $command->action(), entityType: 'sod_rule', entityId: $rule->id, after: $rule->only(['kind', 'left_ref', 'right_ref', 'description'])));

        return ['data' => AccessQueries::presentSodRule($rule)];
    }
}

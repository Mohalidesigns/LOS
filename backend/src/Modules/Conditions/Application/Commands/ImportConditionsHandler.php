<?php

declare(strict_types=1);

namespace Fundly\Modules\Conditions\Application\Commands;

use Fundly\Modules\Application\Contracts\ApplicationLifecycle;
use Fundly\Modules\Application\Contracts\CanonicalStatus;
use Fundly\Modules\Approval\Contracts\ApprovalRegister;
use Fundly\Modules\Conditions\Application\ConditionFile;
use Fundly\Modules\Conditions\Domain\ConditionStatus;
use Fundly\Modules\Conditions\Infrastructure\Models\Condition;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Exceptions\DomainRuleViolation;

final class ImportConditionsHandler implements CommandHandler
{
    public function __construct(private readonly ConditionFile $file, private readonly ApprovalRegister $approvals, private readonly ApplicationLifecycle $lifecycle) {}

    /** @return array{imported: int} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof ImportConditions);
        $app = $this->file->application($command->applicationId);
        if ($app->status !== CanonicalStatus::Accepted) {
            throw new DomainRuleViolation("Conditions open when the offer is accepted; this application is {$app->status->value}.");
        }
        $imported = 0;
        foreach ($this->approvals->current($app->id)->conditions ?? [] as $c) {
            if (Condition::query()->where('application_id', $app->id)->where('source_ref', $c->id)->exists()) {
                continue;
            }
            (new Condition)->forceFill([
                'application_id' => $app->id, 'kind' => $c->kind, 'source' => 'approval', 'source_ref' => $c->id,
                'description' => $c->description, 'mandatory' => true, 'status' => ConditionStatus::Open->value, 'created_by' => $context->principal->id,
            ])->save();
            $imported++;
        }
        $context->audit(new AuditEntry(action: $command->action(), entityType: 'application', entityId: $app->id, after: ['imported' => $imported]));
        $this->lifecycle->advance($app->id, CanonicalStatus::ConditionsPrecedent, 'CONDITIONS_OPENED', "{$imported} condition(s) from the approval are now tracked.", $context->principal);
        $this->file->advanceIfReady($app->id, $context->principal);

        return ['imported' => $imported];
    }
}

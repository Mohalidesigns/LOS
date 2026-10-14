<?php

declare(strict_types=1);

namespace Fundly\Modules\Collateral\Application\Commands;

use Fundly\Modules\Access\Contracts\AuthorityDirectory;
use Fundly\Modules\Collateral\Application\CollateralFile;
use Fundly\Modules\Collateral\Application\CollateralQueries;
use Fundly\Modules\Collateral\Infrastructure\Models\PerfectionStep;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Exceptions\DomainRuleViolation;
use Fundly\Shared\Exceptions\ValidationFailed;

final class UpdatePerfectionStepHandler implements CommandHandler
{
    public function __construct(private readonly CollateralFile $file, private readonly AuthorityDirectory $directory, private readonly CollateralQueries $queries) {}

    /** @return array{data: array<string, mixed>} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof UpdatePerfectionStep);
        $s = $this->file->step($command->stepId);
        $c = $this->file->collateral($s->collateral_id);
        $this->file->assertOpen($this->file->application($c->origin_application_id));
        if (! in_array($s->status, PerfectionStep::OPEN, true)) {
            throw new DomainRuleViolation("This step is already {$s->status}.");
        }
        if ($command->responsibleUserId !== null && $this->directory->names([$command->responsibleUserId]) === []) {
            throw ValidationFailed::with(['responsible_user_id' => 'The responsible party must be a staff user.']);
        }
        $before = ['responsible_user_id' => $s->responsible_user_id, 'due_date' => $s->due_date?->toDateString(), 'status' => $s->status];
        $s->forceFill([
            'responsible_user_id' => $command->responsibleUserId ?? $s->responsible_user_id,
            'due_date' => $command->dueDate ?? $s->due_date?->toDateString(),
            'status' => $command->start && $s->status === 'pending' ? 'in_progress' : $s->status,
        ])->save();
        $context->audit(new AuditEntry(action: $command->action(), entityType: 'collateral', entityId: $c->id, before: $before, after: [
            'step_id' => $s->id, 'code' => $s->code, 'responsible_user_id' => $s->responsible_user_id, 'due_date' => $s->due_date?->toDateString(), 'status' => $s->status,
        ]));

        return ['data' => $this->queries->item($c)];
    }
}

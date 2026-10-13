<?php

declare(strict_types=1);

namespace Fundly\Modules\Conditions\Application\Commands;

use Fundly\Modules\Access\Contracts\AuthorityDirectory;
use Fundly\Modules\Conditions\Application\ConditionFile;
use Fundly\Modules\Conditions\Application\ConditionQueries;
use Fundly\Modules\Conditions\Domain\ConditionStatus;
use Fundly\Modules\Conditions\Infrastructure\Models\Condition;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Exceptions\ValidationFailed;

final class AddConditionHandler implements CommandHandler
{
    public function __construct(private readonly ConditionFile $file, private readonly AuthorityDirectory $directory, private readonly ConditionQueries $queries) {}

    /** @return array{data: array<string, mixed>} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof AddCondition);
        $app = $this->file->application($command->applicationId);
        $this->file->assertWorkable($app, $command->kind);
        if ($command->ownerId !== null && $this->directory->names([$command->ownerId]) === []) {
            throw ValidationFailed::with(['owner_id' => 'The owner must be a staff user.']);
        }
        $c = new Condition;
        $c->forceFill([
            'application_id' => $app->id, 'kind' => $command->kind, 'source' => 'manual', 'description' => trim((string) $command->description),
            'mandatory' => $command->mandatory, 'owner_id' => $command->ownerId, 'due_date' => $command->dueDate,
            'status' => ConditionStatus::Open->value, 'created_by' => $context->principal->id,
        ])->save();
        $context->audit(new AuditEntry(action: $command->action(), entityType: 'condition', entityId: $c->id, after: [
            'application_id' => $app->id, 'kind' => $c->kind, 'mandatory' => $c->mandatory, 'owner_id' => $c->owner_id, 'due_date' => $command->dueDate, 'description' => $c->description,
        ]));

        return ['data' => $this->queries->condition($c)];
    }
}

<?php

declare(strict_types=1);

namespace Fundly\Modules\Workflow\Application\Commands;

use Fundly\Modules\Access\Contracts\AuthorityDirectory;
use Fundly\Modules\Workflow\Application\NoteQueries;
use Fundly\Modules\Workflow\Application\WorkflowFile;
use Fundly\Modules\Workflow\Contracts\Events\NoteMentioned;
use Fundly\Modules\Workflow\Infrastructure\Models\CaseNote;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Exceptions\ValidationFailed;
use Fundly\Shared\Security\AuthorizationGate;
use Fundly\Shared\Security\ResourceAttributes;

/**
 * FR-WFL-010: notes are append-only (a correction is a new note). Only
 * originators may write customer-visible notes; @mentions must be people
 * who can see the case.
 */
final class AddCaseNoteHandler implements CommandHandler
{
    public function __construct(private readonly WorkflowFile $file, private readonly AuthorizationGate $gate, private readonly AuthorityDirectory $authority, private readonly NoteQueries $queries) {}

    /** @return array{data: array<string, mixed>} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof AddCaseNote);
        $app = $this->file->application($command->applicationId);
        $resource = new ResourceAttributes(legalEntityId: $app->legalEntityId, orgUnitId: $app->orgUnitId, productId: $app->productId, segment: $app->segment, entityType: 'application', entityId: $app->id);
        if ($command->visibility === 'customer') {
            $this->gate->authorize($context->principal, 'application:originate', $resource);
        }
        $mentions = array_values(array_unique(array_diff($command->mentionUserIds, [$context->principal->id])));
        foreach ($mentions as $i => $userId) {
            if ($this->authority->grantsFor($userId, 'application:view', $resource) === []) {
                throw ValidationFailed::with(["mention_user_ids.{$i}" => 'You can only mention people who can see this application.']);
            }
        }
        $n = new CaseNote;
        $n->forceFill(['application_id' => $app->id, 'author_id' => $context->principal->id, 'body' => trim((string) $command->body), 'visibility' => $command->visibility, 'mentions' => $mentions, 'created_at' => $this->file->now()])->save();
        if ($mentions !== []) {
            $context->raise(new NoteMentioned($context->principal->tenantId, $app->id, $n->id, $context->principal->id, $mentions));
        }
        $context->audit(new AuditEntry(action: $command->action(), entityType: 'application', entityId: $app->id, after: ['note_id' => $n->id, 'visibility' => $n->visibility, 'mentions' => $mentions]));

        return ['data' => $this->queries->present($n)];
    }
}

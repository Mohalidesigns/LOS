<?php

declare(strict_types=1);

namespace Fundly\Modules\Conditions\Application\Commands;

use Fundly\Modules\Conditions\Application\ConditionFile;
use Fundly\Modules\Conditions\Application\ConditionQueries;
use Fundly\Modules\Conditions\Domain\ConditionStatus;
use Fundly\Modules\Document\Contracts\DocumentArchive;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Exceptions\ValidationFailed;

final class SatisfyConditionHandler implements CommandHandler
{
    public function __construct(private readonly ConditionFile $file, private readonly DocumentArchive $archive, private readonly ConditionQueries $queries) {}

    /** @return array{data: array<string, mixed>} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof SatisfyCondition);
        $c = $this->file->condition($command->conditionId);
        $app = $this->file->application($c->application_id);
        $this->file->assertWorkable($app, $c->kind);
        ConditionStatus::from($c->status)->assertCanSatisfy();
        if ($command->tempPath === null && $command->evidenceReference === null) {
            throw ValidationFailed::with(['evidence' => 'Attach the evidence or give a reference to it (FR-CPR-001).']);
        }
        $doc = $command->tempPath === null ? null
            : $this->archive->storeUploaded($app->id, 'CONDITION_EVIDENCE', 'Evidence: '.mb_substr($c->description, 0, 120), $command->tempPath, (string) $command->originalName, $context->principal->id);
        $before = ['status' => $c->status];
        $c->forceFill([
            'status' => ConditionStatus::Satisfied->value,
            'evidence_document_id' => $doc?->documentId, 'evidence_version_id' => $doc?->versionId, 'evidence_reference' => $command->evidenceReference,
            'signoff_note' => $command->note, 'signed_off_by' => $context->principal->id, 'signed_off_at' => $this->file->now(),
        ])->save();
        $context->audit(new AuditEntry(action: $command->action(), entityType: 'condition', entityId: $c->id, before: $before, after: [
            'status' => $c->status, 'evidence_sha256' => $doc?->sha256, 'evidence_reference' => $c->evidence_reference,
        ], reasonText: $command->note));
        $this->file->advanceIfReady($app->id, $context->principal);

        return ['data' => $this->queries->condition($c)];
    }
}

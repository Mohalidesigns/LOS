<?php

declare(strict_types=1);

namespace Fundly\Modules\Collateral\Application\Commands;

use Fundly\Modules\Collateral\Application\CollateralFile;
use Fundly\Modules\Collateral\Application\CollateralQueries;
use Fundly\Modules\Collateral\Infrastructure\Models\PerfectionStep;
use Fundly\Modules\Document\Contracts\DocumentArchive;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Exceptions\DomainRuleViolation;
use Fundly\Shared\Exceptions\ValidationFailed;

final class CompletePerfectionStepHandler implements CommandHandler
{
    public function __construct(private readonly CollateralFile $file, private readonly DocumentArchive $archive, private readonly CollateralQueries $queries) {}

    /** @return array{data: array<string, mixed>} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof CompletePerfectionStep);
        $s = PerfectionStep::query()->lockForUpdate()->findOrFail($this->file->step($command->stepId)->id);
        $c = $this->file->collateral($s->collateral_id);
        $app = $this->file->application($c->origin_application_id);
        $this->file->assertOpen($app);
        if (! in_array($s->status, PerfectionStep::OPEN, true)) {
            throw new DomainRuleViolation("This step is already {$s->status}.");
        }
        if ($command->tempPath === null && $command->evidenceReference === null) {
            throw ValidationFailed::with(['evidence' => 'Attach the evidence or give a reference to it (registry number, receipt).']);
        }
        $doc = $command->tempPath === null ? null
            : $this->archive->storeUploaded($app->id, 'PERFECTION_EVIDENCE', "{$s->name}: ".mb_substr($c->description, 0, 100), $command->tempPath, (string) $command->originalName, $context->principal->id);
        $before = ['status' => $s->status];
        $s->forceFill([
            'status' => 'completed', 'evidence_document_id' => $doc?->documentId, 'evidence_version_id' => $doc?->versionId, 'evidence_reference' => $command->evidenceReference,
            'note' => $command->note, 'completed_by' => $context->principal->id, 'completed_at' => $this->file->now(),
        ])->save();
        $context->audit(new AuditEntry(action: $command->action(), entityType: 'collateral', entityId: $c->id, before: $before, after: [
            'step_id' => $s->id, 'code' => $s->code, 'timing' => $s->timing, 'status' => $s->status, 'evidence_sha256' => $doc?->sha256, 'evidence_reference' => $s->evidence_reference,
        ], reasonText: $command->note));

        return ['data' => $this->queries->item($c)];
    }
}

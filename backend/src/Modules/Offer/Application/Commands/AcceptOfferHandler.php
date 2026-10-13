<?php

declare(strict_types=1);

namespace Fundly\Modules\Offer\Application\Commands;

use Fundly\Modules\Application\Contracts\ApplicationLifecycle;
use Fundly\Modules\Application\Contracts\CanonicalStatus;
use Fundly\Modules\Document\Contracts\DocumentArchive;
use Fundly\Modules\Offer\Application\OfferFile;
use Fundly\Modules\Offer\Application\OfferQueries;
use Fundly\Modules\Offer\Contracts\Events\OfferAccepted;
use Fundly\Modules\Offer\Infrastructure\Models\Agreement;
use Fundly\Modules\Offer\Infrastructure\Models\OfferSignatory;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Exceptions\DomainRuleViolation;
use Fundly\Shared\Exceptions\ValidationFailed;

final class AcceptOfferHandler implements CommandHandler
{
    public function __construct(private readonly OfferFile $file, private readonly DocumentArchive $archive, private readonly ApplicationLifecycle $lifecycle, private readonly OfferQueries $queries) {}

    /** @return array{data: array<string, mixed>} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof AcceptOffer);
        $offer = $this->file->offer($command->offerId);
        $app = $this->file->application($offer->application_id);
        $now = $this->file->now();
        if ($offer->status !== 'issued' || $app->status !== CanonicalStatus::OfferIssued) {
            throw new DomainRuleViolation("Only a live issued offer can be accepted; this one is {$offer->status}.");
        }
        if ($offer->valid_until->lessThan($now)) {
            throw new DomainRuleViolation('The offer has expired and cannot be accepted (FR-OFR-007).');
        }
        $signatories = OfferSignatory::query()->where('offer_id', $offer->id)->orderBy('position')->get();
        $missing = $signatories->whereNull('signed_at')->pluck('display_name')->all();
        if ($missing !== []) {
            throw new DomainRuleViolation('Every signatory must sign before acceptance. Still to sign: '.implode(', ', $missing).'.', ['blockers' => array_values($missing)]);
        }
        if ($command->tempPath === null) {
            throw ValidationFailed::with(['file' => 'Upload the executed (signed) agreement.']);
        }
        $doc = $this->archive->storeUploaded($app->id, 'EXECUTED_AGREEMENT', "Executed agreement · offer #{$offer->sequence}", $command->tempPath, $command->originalName, $context->principal->id);
        $agreement = new Agreement;
        $agreement->forceFill([
            'application_id' => $app->id,
            'offer_id' => $offer->id,
            'template_version_id' => $offer->template_version_id,
            'execution_method' => $offer->execution_method,
            'document_id' => $doc->documentId,
            'document_version_id' => $doc->versionId,
            'sha256' => $doc->sha256,
            'offer_document_sha256' => $offer->document_sha256,
            'signatures' => $signatories->map(static fn (OfferSignatory $s): array => [
                'position' => $s->position, 'role' => $s->role, 'party_id' => $s->party_id, 'name' => $s->display_name,
                'method' => $s->method, 'signed_at' => $s->signed_at?->toIso8601String(), 'witness_name' => $s->witness_name,
            ])->values()->all(),
            'recorded_by' => $context->principal->id,
            'executed_at' => $now,
        ])->save();
        $offer->forceFill(['status' => 'accepted', 'decided_by' => $context->principal->id, 'decided_at' => $now])->save();
        $context->audit(new AuditEntry(action: $command->action(), entityType: 'offer', entityId: $offer->id, after: [
            'agreement_id' => $agreement->id, 'executed_sha256' => $doc->sha256, 'offer_document_sha256' => $offer->document_sha256, 'template_version_id' => $offer->template_version_id,
        ]));
        $this->lifecycle->advance($app->id, CanonicalStatus::Accepted, 'OFFER_ACCEPTED', "Offer #{$offer->sequence} executed ({$offer->execution_method}); agreement sha256 ".substr($doc->sha256, 0, 12).'…', $context->principal);
        $context->raise(new OfferAccepted($context->principal->tenantId, $app->id, $offer->id, $agreement->id));

        return ['data' => $this->queries->present($offer)];
    }
}

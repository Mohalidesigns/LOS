<?php

declare(strict_types=1);

namespace Fundly\Modules\Document\Application\Commands;

use Fundly\Integration\Ports\MalwareScan\MalwareScanOperations;
use Fundly\Integration\Ports\MalwareScan\MalwareScanPort;
use Fundly\Integration\Ports\MalwareScan\ScanVerdict;
use Fundly\Integration\Runtime\IntegrationGateway;
use Fundly\Modules\Application\Contracts\ApplicationReader;
use Fundly\Modules\Document\Application\DocumentPresenter;
use Fundly\Modules\Document\Contracts\Events\ChecklistChanged;
use Fundly\Modules\Document\Domain\ChecklistStatus;
use Fundly\Modules\Document\Domain\UploadPolicy;
use Fundly\Modules\Document\Infrastructure\DocumentVault;
use Fundly\Modules\Document\Infrastructure\Models\ChecklistItem;
use Fundly\Modules\Document\Infrastructure\Models\Document;
use Fundly\Modules\Document\Infrastructure\Models\DocumentVersion;
use Fundly\Modules\Party\Contracts\PartyDirectory;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Clock\Clock;
use Fundly\Shared\Exceptions\DomainRuleViolation;
use Fundly\Shared\Exceptions\NotFound;
use Fundly\Shared\Exceptions\ValidationFailed;
use Fundly\Shared\Id\UuidV7;

/**
 * Order matters (TRD §5.2): hash the bytes → scan → only a clean file is
 * encrypted and stored. An infected file is recorded (hash, signature) as
 * quarantined evidence and its bytes are discarded, so no user can ever read it.
 */
final class UploadDocumentHandler implements CommandHandler
{
    public function __construct(
        private readonly ApplicationReader $applications,
        private readonly IntegrationGateway $gateway,
        private readonly DocumentVault $vault,
        private readonly PartyDirectory $parties,
        private readonly Clock $clock,
    ) {}

    /** @return array{data: array<string, mixed>} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof UploadDocument);
        $app = $this->applications->find($command->applicationId) ?? throw new NotFound('Application not found.');
        if ($app->status->isTerminal()) {
            throw new DomainRuleViolation("The application is {$app->status->value}; it no longer takes documents.");
        }
        $bytes = @file_get_contents($command->tempPath);
        if (! is_string($bytes)) {
            throw ValidationFailed::with(['file' => 'The upload could not be read.']);
        }
        $sniffed = (string) (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        /** @var array<string, string> $allowed */
        $allowed = (array) config('fundly.documents.mime_types', []);
        UploadPolicy::assertAcceptable($sniffed, strlen($bytes), (int) config('fundly.documents.max_bytes'), $allowed);
        $sha256 = hash('sha256', $bytes);

        $item = null;
        if ($command->checklistItemId !== null) {
            $item = ChecklistItem::query()->lockForUpdate()->find($command->checklistItemId);
            if ($item === null || $item->application_id !== $app->id) {
                throw ValidationFailed::with(['checklist_item_id' => 'The checklist item does not belong to this application.']);
            }
            ChecklistStatus::from($item->status)->assertCanReceive();
        }
        if ($command->partyId !== null && ! $this->partyOnApplication($command->partyId, array_keys($app->applicants))) {
            throw ValidationFailed::with(['party_id' => 'The party is not on this application.']);
        }

        /** @var ScanVerdict $verdict */
        $verdict = $this->gateway->call(
            MalwareScanPort::PORT,
            MalwareScanPort::OP_SCAN,
            MalwareScanOperations::policy(),
            static function (object $adapter) use ($bytes): ScanVerdict {
                assert($adapter instanceof MalwareScanPort);

                return $adapter->scan($bytes);
            },
            ['sha256' => $sha256, 'size_bytes' => strlen($bytes), 'application_id' => $app->id],
        );

        $document = $item?->document_id !== null ? Document::query()->find($item->document_id) : null;
        if ($document === null) {
            $document = new Document;
            $document->forceFill([
                'application_id' => $app->id,
                'checklist_item_id' => $item?->id,
                'party_id' => $command->partyId,
                'document_type' => $command->documentType,
                'title' => $command->title ?? ($item->name ?? UploadPolicy::safeFilename($command->originalName)),
                'created_by' => $context->principal->id,
            ])->save();
        }
        $versionNo = (int) DocumentVersion::query()->where('document_id', $document->id)->max('version_no') + 1;
        $versionId = UuidV7::generate();
        $stored = $verdict->clean ? $this->vault->put($versionId, $sha256, $bytes) : ['storage_key' => null, 'key_version' => null];
        $version = new DocumentVersion;
        $version->forceFill([
            'id' => $versionId,
            'document_id' => $document->id,
            'version_no' => $versionNo,
            'filename' => UploadPolicy::safeFilename($command->originalName),
            'mime_type' => $sniffed,
            'size_bytes' => strlen($bytes),
            'sha256' => $sha256,
            'scan_status' => $verdict->clean ? 'clean' : 'infected',
            'scan_signature' => $verdict->signature,
            'scanner' => $verdict->engine,
            'storage_key' => $stored['storage_key'],
            'key_version' => $stored['key_version'],
            'uploaded_by' => $context->principal->id,
            'uploaded_at' => $this->clock->now(),
        ])->save();

        if ($verdict->clean && $item !== null) {
            $item->forceFill(['status' => ChecklistStatus::Received->value, 'document_id' => $document->id, 'rejection_reason' => null, 'verified_by' => null, 'verified_at' => null, 'valid_until' => null])->save();
            $context->raise(new ChecklistChanged($context->principal->tenantId, $app->id, $item->code, $item->status));
        }
        $duplicates = DocumentPresenter::duplicates($version, $document);
        $context->audit(new AuditEntry(
            action: $verdict->clean ? 'document.uploaded' : 'document.quarantined',
            entityType: 'document',
            entityId: $document->id,
            after: [
                'application_id' => $app->id, 'version_id' => $versionId, 'version_no' => $versionNo, 'sha256' => $sha256, 'mime_type' => $sniffed,
                'size_bytes' => strlen($bytes), 'scan_status' => $version->scan_status, 'scan_signature' => $verdict->signature,
                'checklist_item' => $item?->code, 'duplicate_of' => array_column($duplicates, 'document_id'),
            ],
        ));

        $versions = array_values(DocumentVersion::query()->where('document_id', $document->id)->orderBy('version_no')->get()->all());

        return ['data' => DocumentPresenter::document($document->refresh(), $versions)];
    }

    /**
     * An applicant, or a director / signatory / beneficial owner of one.
     *
     * @param  list<string>  $applicantIds
     */
    private function partyOnApplication(string $partyId, array $applicantIds): bool
    {
        if (in_array($partyId, $applicantIds, true)) {
            return true;
        }
        foreach ($applicantIds as $id) {
            foreach ($this->parties->kycProfile($id)->relatedIndividuals ?? [] as $rel) {
                if ($rel['party_id'] === $partyId) {
                    return true;
                }
            }
        }

        return false;
    }
}

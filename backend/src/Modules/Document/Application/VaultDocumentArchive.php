<?php

declare(strict_types=1);

namespace Fundly\Modules\Document\Application;

use Fundly\Integration\Ports\MalwareScan\MalwareScanOperations;
use Fundly\Integration\Ports\MalwareScan\MalwareScanPort;
use Fundly\Integration\Ports\MalwareScan\ScanVerdict;
use Fundly\Integration\Runtime\IntegrationGateway;
use Fundly\Modules\Document\Contracts\ArchivedDocument;
use Fundly\Modules\Document\Contracts\DocumentArchive;
use Fundly\Modules\Document\Contracts\DocumentReference;
use Fundly\Modules\Document\Domain\UploadPolicy;
use Fundly\Modules\Document\Infrastructure\DocumentVault;
use Fundly\Modules\Document\Infrastructure\Models\Document;
use Fundly\Modules\Document\Infrastructure\Models\DocumentVersion;
use Fundly\Shared\Clock\Clock;
use Fundly\Shared\Exceptions\ValidationFailed;
use Fundly\Shared\Id\UuidV7;

final class VaultDocumentArchive implements DocumentArchive
{
    public function __construct(private readonly DocumentVault $vault, private readonly IntegrationGateway $gateway, private readonly Clock $clock) {}

    public function storeGenerated(string $applicationId, string $documentType, string $title, string $filename, string $mimeType, string $bytes, string $actorId): ArchivedDocument
    {
        // Generated in-process, so there is nothing to scan; the scanner field says so.
        return $this->store($applicationId, $documentType, $title, UploadPolicy::safeFilename($filename), $mimeType, $bytes, $actorId, 'platform-generated');
    }

    public function storeUploaded(string $applicationId, string $documentType, string $title, string $tempPath, string $originalName, string $actorId): ArchivedDocument
    {
        $bytes = @file_get_contents($tempPath);
        if (! is_string($bytes)) {
            throw ValidationFailed::with(['file' => 'The upload could not be read.']);
        }
        $sniffed = (string) (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        /** @var array<string, string> $allowed */
        $allowed = (array) config('fundly.documents.mime_types', []);
        UploadPolicy::assertAcceptable($sniffed, strlen($bytes), (int) config('fundly.documents.max_bytes'), $allowed);
        $sha256 = hash('sha256', $bytes);
        /** @var ScanVerdict $verdict */
        $verdict = $this->gateway->call(
            MalwareScanPort::PORT,
            MalwareScanPort::OP_SCAN,
            MalwareScanOperations::policy(),
            static function (object $adapter) use ($bytes): ScanVerdict {
                assert($adapter instanceof MalwareScanPort);

                return $adapter->scan($bytes);
            },
            ['sha256' => $sha256, 'size_bytes' => strlen($bytes), 'application_id' => $applicationId],
        );
        if (! $verdict->clean) {
            throw ValidationFailed::with(['file' => "The file failed the malware scan ({$verdict->signature}) and was not stored."]);
        }

        return $this->store($applicationId, $documentType, $title, UploadPolicy::safeFilename($originalName), $sniffed, $bytes, $actorId, $verdict->engine);
    }

    public function references(string $applicationId): array
    {
        $out = [];
        foreach (Document::query()->where('application_id', $applicationId)->orderBy('created_at')->orderBy('id')->get() as $doc) {
            $v = DocumentVersion::query()->where('document_id', $doc->id)->where('scan_status', 'clean')->orderByDesc('version_no')->first();
            if ($v !== null) {
                $out[] = new DocumentReference($doc->id, $v->id, $v->version_no, $doc->document_type, $doc->title, $v->filename, $v->mime_type, $v->sha256, $v->uploaded_at->toDateTimeImmutable());
            }
        }

        return $out;
    }

    public function content(string $versionId): ?string
    {
        $v = preg_match('/^[0-9a-f-]{36}$/', $versionId) === 1 ? DocumentVersion::query()->find($versionId) : null;
        if ($v === null || $v->scan_status !== 'clean' || $v->storage_key === null || $v->key_version === null) {
            return null;
        }

        return $this->vault->get($v->id, $v->storage_key, (int) $v->key_version);
    }

    private function store(string $applicationId, string $documentType, string $title, string $filename, string $mimeType, string $bytes, string $actorId, string $scanner): ArchivedDocument
    {
        $sha256 = hash('sha256', $bytes);
        $document = new Document;
        $document->forceFill(['application_id' => $applicationId, 'document_type' => $documentType, 'title' => $title, 'created_by' => $actorId])->save();
        $versionId = UuidV7::generate();
        $stored = $this->vault->put($versionId, $sha256, $bytes);
        (new DocumentVersion)->forceFill([
            'id' => $versionId,
            'document_id' => $document->id,
            'version_no' => 1,
            'filename' => $filename,
            'mime_type' => $mimeType,
            'size_bytes' => strlen($bytes),
            'sha256' => $sha256,
            'scan_status' => 'clean',
            'scan_signature' => null,
            'scanner' => $scanner,
            'storage_key' => $stored['storage_key'],
            'key_version' => $stored['key_version'],
            'uploaded_by' => $actorId,
            'uploaded_at' => $this->clock->now(),
        ])->save();

        return new ArchivedDocument($document->id, $versionId, $sha256, strlen($bytes), $mimeType, $filename);
    }
}

<?php

declare(strict_types=1);

namespace Fundly\Modules\Audit\Application;

use DateTimeZone;
use Fundly\Modules\Application\Contracts\ApplicationReader;
use Fundly\Modules\Document\Contracts\DocumentArchive;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Audit\AuditTrail;
use Fundly\Shared\Clock\Clock;
use Fundly\Shared\Exceptions\NotFound;
use Fundly\Shared\Security\Principal;
use Illuminate\Database\ConnectionInterface;
use RuntimeException;
use ZipArchive;

/**
 * Evidence pack v1 (FR-AUD-010, TRD §5.4): a ZIP with one JSON file per
 * contributed section (timeline, decisions, approvals, screening,
 * communications, ...), the latest clean version of every document, and
 * `manifest.json` listing the SHA-256 and size of every file. Built on demand
 * and synchronously (D-087); the export itself is audited with the manifest
 * hash. Ed25519 signing of the manifest is P5 (`signature: null`).
 */
final class EvidencePackBuilder
{
    public const FORMAT = 'fundly.evidence-pack/1';

    public function __construct(
        private readonly EvidenceRegistry $sources,
        private readonly ApplicationReader $applications,
        private readonly DocumentArchive $documents,
        private readonly AuditTrail $audit,
        private readonly ConnectionInterface $db,
        private readonly Clock $clock,
    ) {}

    /** @return array{bytes: string, filename: string, manifest_sha256: string} */
    public function build(string $applicationId, Principal $principal): array
    {
        $app = preg_match('/^[0-9a-f-]{36}$/', $applicationId) === 1 ? $this->applications->find($applicationId) : null;
        if ($app === null) {
            throw new NotFound('Application not found.');
        }
        $files = [];
        foreach ($this->sources->collect($applicationId, $principal) as $section => $data) {
            $files["{$section}.json"] = self::json($data);
        }
        foreach ($this->documents->references($applicationId) as $ref) {
            $bytes = $this->documents->content($ref->versionId);
            if ($bytes === null || ! hash_equals($ref->sha256, hash('sha256', $bytes))) {
                throw new RuntimeException("Document version {$ref->versionId} does not match its recorded SHA-256.");
            }
            $files['documents/'.$ref->versionId.'/'.self::safeName($ref->filename)] = $bytes;
        }

        $generatedAt = $this->clock->now()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z');
        $entries = [];
        foreach ($files as $path => $bytes) {
            $entries[] = ['path' => $path, 'sha256' => hash('sha256', $bytes), 'bytes' => strlen($bytes)];
        }
        $manifest = self::json([
            'format' => self::FORMAT,
            'application' => ['id' => $app->id, 'reference' => $app->reference, 'status' => $app->status->value],
            'generated_at' => $generatedAt,
            'generated_by' => $principal->id,
            'hash_algorithm' => 'sha256',
            'files' => $entries,
            'signature' => null,
        ]);
        $manifestSha = hash('sha256', $manifest);

        $tmp = tempnam(sys_get_temp_dir(), 'evp');
        if ($tmp === false) {
            throw new RuntimeException('Cannot create a temporary file for the evidence pack.');
        }
        try {
            $zip = new ZipArchive;
            if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Cannot open the evidence pack archive.');
            }
            $zip->addFromString('manifest.json', $manifest);
            foreach ($files as $path => $bytes) {
                $zip->addFromString($path, $bytes);
            }
            $zip->close();
            $bytes = (string) file_get_contents($tmp);
        } finally {
            @unlink($tmp);
        }

        $this->db->transaction(fn () => $this->audit->record(new AuditEntry(
            action: 'audit.evidence_pack.exported',
            entityType: 'application',
            entityId: $app->id,
            after: ['format' => self::FORMAT, 'manifest_sha256' => $manifestSha, 'files' => count($entries), 'archive_sha256' => hash('sha256', $bytes)],
            permission: 'audit:read',
        )));

        return ['bytes' => $bytes, 'filename' => "evidence-pack-{$app->reference}.zip", 'manifest_sha256' => $manifestSha];
    }

    private static function json(mixed $data): string
    {
        return json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION)."\n";
    }

    private static function safeName(string $name): string
    {
        $clean = preg_replace('/[^A-Za-z0-9._-]+/', '_', basename($name)) ?? 'file';

        return trim($clean, '._') === '' ? 'file' : $clean;
    }
}

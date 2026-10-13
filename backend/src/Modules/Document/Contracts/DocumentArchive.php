<?php

declare(strict_types=1);

namespace Fundly\Modules\Document\Contracts;

/**
 * Lets other modules file documents on an application (offer letters,
 * executed agreements) in the same encrypted, immutable vault as uploads,
 * so they appear on the case and keep one evidence trail. Callers audit
 * the business action; the archive records only the content.
 */
interface DocumentArchive
{
    /** A document the platform generated itself (never scanned: it did not come from outside). */
    public function storeGenerated(string $applicationId, string $documentType, string $title, string $filename, string $mimeType, string $bytes, string $actorId): ArchivedDocument;

    /**
     * A file a user uploaded: type sniffed, size checked and malware-scanned
     * before it is stored. An infected or unacceptable file is refused.
     */
    public function storeUploaded(string $applicationId, string $documentType, string $title, string $tempPath, string $originalName, string $actorId): ArchivedDocument;

    /** Decrypted bytes of a clean version (for delivery by the owning module); null when unknown or not readable. */
    public function content(string $versionId): ?string;
}

<?php

declare(strict_types=1);

namespace Fundly\Modules\Document\Contracts;

/** A document version stored in the application's document vault. */
final readonly class ArchivedDocument
{
    public function __construct(
        public string $documentId,
        public string $versionId,
        public string $sha256,
        public int $sizeBytes,
        public string $mimeType,
        public string $filename,
    ) {}
}

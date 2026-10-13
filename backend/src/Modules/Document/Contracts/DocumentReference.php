<?php

declare(strict_types=1);

namespace Fundly\Modules\Document\Contracts;

use DateTimeImmutable;

/** A durable reference to the current clean version of a document (FR-HND-002). */
final readonly class DocumentReference
{
    public function __construct(
        public string $documentId,
        public string $versionId,
        public int $versionNo,
        public string $documentType,
        public string $title,
        public string $filename,
        public string $mimeType,
        public string $sha256,
        public DateTimeImmutable $storedAt,
    ) {}
}

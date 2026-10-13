<?php

declare(strict_types=1);

namespace Fundly\Integration\Ports\PdfRender;

/** The bytes stay private so the integration call log records only the hash and size. */
final readonly class RenderedPdf
{
    public string $sha256;

    public int $sizeBytes;

    public function __construct(private string $bytes, public string $engine)
    {
        $this->sha256 = hash('sha256', $bytes);
        $this->sizeBytes = strlen($bytes);
    }

    public function bytes(): string
    {
        return $this->bytes;
    }
}

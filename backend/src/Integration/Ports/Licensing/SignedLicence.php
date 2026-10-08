<?php

declare(strict_types=1);

namespace Fundly\Integration\Ports\Licensing;

use Fundly\Shared\Json\CanonicalJson;

/** A licence document as delivered: the exact signed bytes plus the detached signature. */
final readonly class SignedLicence
{
    public function __construct(public string $document, public string $signature) {}

    /** File format: {"licence": {...}, "signature": "<base64 Ed25519>"} */
    public static function fromFileContents(string $json): self
    {
        $d = json_decode($json, true);
        if (! is_array($d) || ! isset($d['licence'], $d['signature']) || ! is_array($d['licence']) || ! is_string($d['signature'])) {
            throw new LicenceInvalid('The licence file is malformed.');
        }

        return new self(CanonicalJson::encode($d['licence']), $d['signature']);
    }

    public function toFileContents(): string
    {
        return (string) json_encode(['licence' => json_decode($this->document, true), 'signature' => $this->signature], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}

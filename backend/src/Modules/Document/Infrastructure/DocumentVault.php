<?php

declare(strict_types=1);

namespace Fundly\Modules\Document\Infrastructure;

use Fundly\Shared\Crypto\TenantKeyRing;
use Fundly\Shared\Tenancy\TenantContext;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Contracts\Filesystem\Filesystem;
use RuntimeException;

/**
 * Content store (FR-DOC-005): each version is encrypted with the tenant's
 * document key (XChaCha20-Poly1305, version id bound as associated data) and
 * written once under a key that is never reused. Overwrites are refused.
 */
final class DocumentVault
{
    private const PURPOSE = 'document_dek';

    public function __construct(
        private readonly FilesystemFactory $filesystems,
        private readonly TenantKeyRing $keys,
        private readonly TenantContext $tenant,
    ) {}

    /** @return array{storage_key: string, key_version: int} */
    public function put(string $versionId, string $sha256, string $bytes): array
    {
        $tenant = $this->tenant->requireId();
        ['version' => $keyVersion, 'key' => $key] = $this->keys->active(self::PURPOSE);
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $cipher = $nonce.sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($bytes, $this->aad($tenant, $versionId), $nonce, $key);
        $path = sprintf('%s/%s/%s/%s.bin', $tenant, substr($sha256, 0, 2), substr($sha256, 2, 2), $versionId);
        $disk = $this->disk();
        if ($disk->exists($path)) {
            throw new RuntimeException('Refusing to overwrite stored document content.');
        }
        $disk->put($path, $cipher);

        return ['storage_key' => $path, 'key_version' => $keyVersion];
    }

    public function get(string $versionId, string $storageKey, int $keyVersion): string
    {
        $raw = $this->disk()->get($storageKey);
        if (! is_string($raw) || strlen($raw) <= SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES) {
            throw new RuntimeException('Stored document content is missing or truncated.');
        }
        $plain = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
            substr($raw, SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES),
            $this->aad($this->tenant->requireId(), $versionId),
            substr($raw, 0, SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES),
            $this->keys->version(self::PURPOSE, $keyVersion),
        );
        if ($plain === false) {
            throw new RuntimeException('Document content failed authentication (tampered or wrong tenant).');
        }

        return $plain;
    }

    private function disk(): Filesystem
    {
        return $this->filesystems->disk((string) config('fundly.documents.disk', 'documents'));
    }

    private function aad(string $tenant, string $versionId): string
    {
        return "tenant:{$tenant}|document_version:{$versionId}";
    }
}

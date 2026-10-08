<?php

declare(strict_types=1);

namespace Fundly\Integration\Adapters\LocalKeyfile;

use Fundly\Integration\Ports\KeyManagement\KeyManagementFailure;
use Fundly\Integration\Ports\KeyManagement\KeyManagementPort;
use Fundly\Integration\Ports\KeyManagement\WrappedKey;

/**
 * MVP KeyManagementPort adapter: a 32-byte KEK in a root-owned 0400 file
 * (TRD §8.3). Wrapping is XChaCha20-Poly1305 with the KEK id and caller
 * context as associated data, so a wrapped key cannot be replayed into
 * another tenant or purpose.
 *
 * @param  array<string, string>  $keks  map of KEK id => raw 32-byte key (current + retired)
 */
final class LocalKeyfileKms implements KeyManagementPort
{
    /** @param array<string, string> $keks */
    public function __construct(private readonly string $currentKekId, private readonly array $keks)
    {
        if (! isset($keks[$currentKekId])) {
            throw new KeyManagementFailure("Current KEK {$currentKekId} is not loaded.");
        }
        foreach ($keks as $id => $key) {
            if (strlen($key) !== SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES) {
                throw new KeyManagementFailure("KEK {$id} must be 32 bytes.");
            }
        }
    }

    public static function fromFile(string $path, string $kekId): self
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new KeyManagementFailure('KEK file is missing or unreadable.');
        }
        $raw = base64_decode(trim((string) file_get_contents($path)), true);
        if ($raw === false) {
            throw new KeyManagementFailure('KEK file must contain base64.');
        }

        return new self($kekId, [$kekId => $raw]);
    }

    public static function generateKeyFile(string $path): void
    {
        $dir = dirname($path);
        if (! is_dir($dir) && ! mkdir($dir, 0700, true) && ! is_dir($dir)) {
            throw new KeyManagementFailure("Cannot create {$dir}.");
        }
        if (file_exists($path)) {
            throw new KeyManagementFailure('Refusing to overwrite an existing KEK file.');
        }
        file_put_contents($path, base64_encode(sodium_crypto_aead_xchacha20poly1305_ietf_keygen()));
        chmod($path, 0400);
    }

    public function currentKekId(): string
    {
        return $this->currentKekId;
    }

    public function wrap(string $dataKey, string $context): WrappedKey
    {
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $ct = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($dataKey, $this->aad($this->currentKekId, $context), $nonce, $this->keks[$this->currentKekId]);

        return new WrappedKey($this->currentKekId, base64_encode($nonce.$ct));
    }

    public function unwrap(WrappedKey $wrapped, string $context): string
    {
        $kek = $this->keks[$wrapped->kekId] ?? throw new KeyManagementFailure("KEK {$wrapped->kekId} is not available.");
        $raw = base64_decode($wrapped->ciphertext, true);
        if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES) {
            throw new KeyManagementFailure('Malformed wrapped key.');
        }
        $nonce = substr($raw, 0, SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $ct = substr($raw, SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $key = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($ct, $this->aad($wrapped->kekId, $context), $nonce, $kek);
        if ($key === false) {
            throw new KeyManagementFailure('Unwrap failed: wrong KEK or context, or tampered key.');
        }

        return $key;
    }

    public function rewrap(WrappedKey $wrapped, string $context): WrappedKey
    {
        return $this->wrap($this->unwrap($wrapped, $context), $context);
    }

    private function aad(string $kekId, string $context): string
    {
        return 'fundly-kek|'.$kekId.'|'.$context;
    }
}

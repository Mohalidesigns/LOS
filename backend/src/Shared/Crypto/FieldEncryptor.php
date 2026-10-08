<?php

declare(strict_types=1);

namespace Fundly\Shared\Crypto;

use Fundly\Shared\Tenancy\TenantContext;
use RuntimeException;

/**
 * Field-level encryption for sensitive attributes (FR-SEC-017, TRD §5.3):
 * XChaCha20-Poly1305 under the tenant's data key, with tenant + field bound
 * as associated data, plus a keyed blind index for exact-match lookup.
 *
 * Ciphertext format: "fe1.<keyVersion>.<base64url(nonce || ciphertext)>".
 */
final class FieldEncryptor
{
    private const PURPOSE_DEK = 'pii_dek';

    private const PURPOSE_INDEX = 'blind_index';

    public function __construct(private readonly TenantKeyRing $keys, private readonly TenantContext $tenant)
    {
    }

    public function encrypt(string $plaintext, string $field): string
    {
        ['version' => $version, 'key' => $key] = $this->keys->active(self::PURPOSE_DEK);
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $ct = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($plaintext, $this->aad($field), $nonce, $key);

        return 'fe1.'.$version.'.'.rtrim(strtr(base64_encode($nonce.$ct), '+/', '-_'), '=');
    }

    public function decrypt(string $ciphertext, string $field): string
    {
        $parts = explode('.', $ciphertext, 3);
        if (count($parts) !== 3 || $parts[0] !== 'fe1' || ! ctype_digit($parts[1])) {
            throw new RuntimeException('Unsupported ciphertext format.');
        }
        $raw = base64_decode(strtr($parts[2], '-_', '+/'), true);
        if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES) {
            throw new RuntimeException('Malformed ciphertext.');
        }
        $key = $this->keys->version(self::PURPOSE_DEK, (int) $parts[1]);
        $plain = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
            substr($raw, SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES),
            $this->aad($field),
            substr($raw, 0, SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES),
            $key,
        );
        if ($plain === false) {
            throw new RuntimeException('Decryption failed: wrong tenant, field or tampered ciphertext.');
        }

        return $plain;
    }

    /** Deterministic per tenant + field, so equal values can be matched without decrypting. */
    public function blindIndex(string $value, string $field): string
    {
        ['key' => $key] = $this->keys->active(self::PURPOSE_INDEX);
        $normalised = mb_strtolower(preg_replace('/\s+/', '', $value) ?? $value);

        return hash_hmac('sha256', $field."\0".$normalised, $key);
    }

    private function aad(string $field): string
    {
        return 'tenant:'.$this->tenant->requireId().'|field:'.$field;
    }
}

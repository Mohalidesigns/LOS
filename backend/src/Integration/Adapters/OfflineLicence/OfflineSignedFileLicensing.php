<?php

declare(strict_types=1);

namespace Fundly\Integration\Adapters\OfflineLicence;

use Fundly\Integration\Ports\Licensing\CheckInResult;
use Fundly\Integration\Ports\Licensing\Licence;
use Fundly\Integration\Ports\Licensing\LicenceInvalid;
use Fundly\Integration\Ports\Licensing\LicensingPort;
use Fundly\Integration\Ports\Licensing\SignedLicence;
use Fundly\Shared\Clock\Clock;
use Fundly\Shared\Id\UuidV7;
use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;

/**
 * Offline signed licence file (D-034, MVP): an Ed25519 signature over the
 * canonical JSON licence, verified with libsodium against the vendor public
 * key pinned in configuration. Verified again every time it is loaded from
 * the database, so tampering with the stored row voids the licence.
 */
final class OfflineSignedFileLicensing implements LicensingPort
{
    private ?Licence $cached = null;

    private bool $loaded = false;

    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly InstallationIdentity $installation,
        private readonly Clock $clock,
        private readonly string $publicKeyBase64,
    ) {}

    public function currentLicence(): ?Licence
    {
        if ($this->loaded) {
            return $this->cached;
        }
        $this->loaded = true;
        $row = $this->db->table('licences')->where('status', 'active')->first();
        if ($row === null) {
            return $this->cached = null;
        }
        try {
            return $this->cached = $this->verify(new SignedLicence((string) $row->document, (string) $row->signature));
        } catch (LicenceInvalid) {
            return $this->cached = null;
        }
    }

    public function verify(SignedLicence $signed): Licence
    {
        $publicKey = base64_decode($this->publicKeyBase64, true);
        if ($publicKey === false || strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            throw new LicenceInvalid('No valid vendor licence public key is configured for this installation.', 'licence-key-missing');
        }
        $signature = base64_decode($signed->signature, true);
        if ($signature === false || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES
            || ! sodium_crypto_sign_verify_detached($signature, $signed->document, $publicKey)) {
            throw new LicenceInvalid('The licence signature is not valid.', 'licence-signature-invalid');
        }
        $data = json_decode($signed->document, true);
        if (! is_array($data)) {
            throw new LicenceInvalid('The licence document is not valid JSON.');
        }
        try {
            /** @var array<string, mixed> $data */
            $licence = Licence::fromArray($data);
        } catch (InvalidArgumentException $e) {
            throw new LicenceInvalid($e->getMessage());
        }
        if (! hash_equals($this->installation->fingerprint(), $licence->installationFingerprint)) {
            throw new LicenceInvalid('The licence was issued for a different installation.', 'licence-installation-mismatch');
        }

        return $licence;
    }

    public function install(SignedLicence $signed, ?string $tenantId, ?string $importedBy, ?string $changeRequestId): Licence
    {
        $licence = $this->verify($signed);
        $now = $this->clock->now();
        $this->db->table('licences')->where('status', 'active')->update(['status' => 'superseded']);
        $this->db->table('licences')->insert([
            'id' => UuidV7::generate(),
            'licence_id' => $licence->licenceId,
            'document' => $signed->document,
            'signature' => $signed->signature,
            'status' => 'active',
            'imported_by_tenant_id' => $tenantId,
            'imported_by' => $importedBy,
            'change_request_id' => $changeRequestId,
            'imported_at' => $now,
        ]);
        $this->cached = $licence;
        $this->loaded = true;

        return $licence;
    }

    public function checkIn(): CheckInResult
    {
        return new CheckInResult(false, true, 'Offline licence: online check-in is not used.');
    }

    /** @return array<string, string> */
    public function activationRequest(): array
    {
        return [
            'installation_uuid' => $this->installation->installationUuid(),
            'installation_fingerprint' => $this->installation->fingerprint(),
            'requested_at' => $this->clock->now()->format(DATE_ATOM),
        ];
    }

    public function installationFingerprint(): string
    {
        return $this->installation->fingerprint();
    }

    /** Forget the cached licence (after import or in long-running workers). */
    public function refresh(): void
    {
        $this->cached = null;
        $this->loaded = false;
    }
}

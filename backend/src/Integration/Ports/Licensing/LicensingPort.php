<?php

declare(strict_types=1);

namespace Fundly\Integration\Ports\Licensing;

/**
 * Licensing (TRD §2.6, register I-31, LOS-FR-316). MVP adapter: offline signed
 * file. The Atheris LicensingServer adapter follows once its contract is
 * received (G-48).
 */
interface LicensingPort
{
    /** The installed, signature-verified licence, or null if none. */
    public function currentLicence(): ?Licence;

    /** Verify a delivered licence for *this* installation without installing it. */
    public function verify(SignedLicence $signed): Licence;

    /** Install a verified licence (called by the maker-checker action only). */
    public function install(SignedLicence $signed, ?string $tenantId, ?string $importedBy, ?string $changeRequestId): Licence;

    /** Optional online check-in; offline adapters report "not supported". */
    public function checkIn(): CheckInResult;

    /**
     * Offline activation request to send to the vendor (air-gapped sites).
     *
     * @return array<string, string>
     */
    public function activationRequest(): array;

    public function installationFingerprint(): string;
}

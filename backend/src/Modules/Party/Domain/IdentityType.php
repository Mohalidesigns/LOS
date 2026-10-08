<?php

declare(strict_types=1);

namespace Fundly\Modules\Party\Domain;

/** Identity documents and numbers recorded on a party (FR-CUS-002/003). */
enum IdentityType: string
{
    case Bvn = 'bvn';
    case Nin = 'nin';
    case Passport = 'passport';
    case DriversLicence = 'drivers_licence';
    case VotersCard = 'voters_card';

    /** Format check only; verification goes through the IdentityVerificationPort. */
    public function isWellFormed(string $value): bool
    {
        return match ($this) {
            self::Bvn, self::Nin => preg_match('/^\d{11}$/', $value) === 1,
            self::Passport => preg_match('/^[A-Z][0-9]{8}$/', $value) === 1,
            self::DriversLicence => preg_match('/^[A-Z0-9]{8,16}$/', $value) === 1,
            self::VotersCard => preg_match('/^[A-Z0-9]{9,19}$/', $value) === 1,
        };
    }

    public function formatHint(): string
    {
        return match ($this) {
            self::Bvn, self::Nin => '11 digits',
            self::Passport => 'one letter followed by 8 digits',
            self::DriversLicence => '8 to 16 letters or digits',
            self::VotersCard => '9 to 19 letters or digits',
        };
    }
}

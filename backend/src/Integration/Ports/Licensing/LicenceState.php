<?php

declare(strict_types=1);

namespace Fundly\Integration\Ports\Licensing;

enum LicenceState: string
{
    case Valid = 'valid';
    case Grace = 'grace';
    case Expired = 'expired';
    case NotYetValid = 'not_yet_valid';
    case NotInstalled = 'not_installed';
    case Invalid = 'invalid';

    /** May the installation operate normally (possibly with warnings)? */
    public function permitsOperation(): bool
    {
        return $this === self::Valid || $this === self::Grace;
    }
}

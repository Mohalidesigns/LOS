<?php

declare(strict_types=1);

namespace Fundly\Modules\Compliance\Domain;

enum AlertStatus: string
{
    case Open = 'open';
    case PendingConfirmation = 'pending_confirmation';
    case Cleared = 'cleared';
    case ConfirmedMatch = 'confirmed_match';

    public function isResolved(): bool
    {
        return $this === self::Cleared || $this === self::ConfirmedMatch;
    }
}

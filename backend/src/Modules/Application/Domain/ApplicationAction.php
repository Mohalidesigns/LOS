<?php

declare(strict_types=1);

namespace Fundly\Modules\Application\Domain;

/** Staff actions on an application (TRD §10.2 `/applications/{id}/actions/*`). */
enum ApplicationAction: string
{
    case Submit = 'submit';
    case Withdraw = 'withdraw';
    case Cancel = 'cancel';
    case Hold = 'hold';
    case Resume = 'resume';
    case Return = 'return';
    case Resubmit = 'resubmit';
    case Recommend = 'recommend';

    public function permission(): string
    {
        return match ($this) {
            self::Recommend, self::Return => 'application:recommend',
            default => 'application:originate',
        };
    }

    /** Terminal and pausing actions need a reason code (FR-APP-006, FR-WFL-007/008). */
    public function requiresReason(): bool
    {
        return in_array($this, [self::Withdraw, self::Cancel, self::Hold, self::Return], true);
    }
}

<?php

declare(strict_types=1);

namespace Fundly\Shared\Audit;

enum AuditOutcome: string
{
    case Success = 'success';
    case Denied = 'denied';
    case Failure = 'failure';
}

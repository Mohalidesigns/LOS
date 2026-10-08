<?php

declare(strict_types=1);

namespace Fundly\Shared\Audit;

enum ActorType: string
{
    case User = 'user';
    case ServiceAccount = 'service_account';
    case Partner = 'partner';
    case System = 'system';
    case Anonymous = 'anonymous';
}

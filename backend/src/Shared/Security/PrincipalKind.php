<?php

declare(strict_types=1);

namespace Fundly\Shared\Security;

enum PrincipalKind: string
{
    case Human = 'human';
    case Service = 'service';
    case System = 'system';
}

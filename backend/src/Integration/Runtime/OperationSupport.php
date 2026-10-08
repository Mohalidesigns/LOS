<?php

declare(strict_types=1);

namespace Fundly\Integration\Runtime;

enum OperationSupport: string
{
    case Native = 'native';
    case Emulated = 'emulated';
    case Unsupported = 'unsupported';
}

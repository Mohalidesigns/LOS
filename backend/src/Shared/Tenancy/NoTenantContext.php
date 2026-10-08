<?php

declare(strict_types=1);

namespace Fundly\Shared\Tenancy;

use RuntimeException;

final class NoTenantContext extends RuntimeException
{
}

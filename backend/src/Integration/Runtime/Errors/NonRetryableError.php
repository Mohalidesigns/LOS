<?php

declare(strict_types=1);

namespace Fundly\Integration\Runtime\Errors;

final class NonRetryableError extends IntegrationException
{
    public function errorClass(): ErrorClass
    {
        return ErrorClass::NonRetryable;
    }
}

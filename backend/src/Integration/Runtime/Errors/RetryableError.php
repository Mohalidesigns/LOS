<?php

declare(strict_types=1);

namespace Fundly\Integration\Runtime\Errors;

final class RetryableError extends IntegrationException
{
    public function errorClass(): ErrorClass
    {
        return ErrorClass::Retryable;
    }
}

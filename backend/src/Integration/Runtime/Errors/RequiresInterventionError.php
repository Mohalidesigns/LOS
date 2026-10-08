<?php

declare(strict_types=1);

namespace Fundly\Integration\Runtime\Errors;

final class RequiresInterventionError extends IntegrationException
{
    public function errorClass(): ErrorClass
    {
        return ErrorClass::RequiresIntervention;
    }
}

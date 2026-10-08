<?php

declare(strict_types=1);

namespace Fundly\Modules\Application\Domain;

use Fundly\Shared\Exceptions\DomainRuleViolation;

final class ApplicationRuleViolation extends DomainRuleViolation
{
    public function code(): string
    {
        return 'application-rule-violation';
    }
}

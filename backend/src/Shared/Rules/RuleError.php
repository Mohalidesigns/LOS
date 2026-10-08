<?php

declare(strict_types=1);

namespace Fundly\Shared\Rules;

use Fundly\Shared\Exceptions\DomainRuleViolation;

/** A rule expression failed to parse or evaluate. */
final class RuleError extends DomainRuleViolation
{
    public function code(): string
    {
        return 'rule-expression-error';
    }
}

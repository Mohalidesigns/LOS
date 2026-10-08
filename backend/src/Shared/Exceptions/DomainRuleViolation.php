<?php

declare(strict_types=1);

namespace Fundly\Shared\Exceptions;

class DomainRuleViolation extends ProblemException
{
    public function status(): int
    {
        return 422;
    }

    public function type(): string
    {
        return 'domain-rule-violation';
    }

    public function title(): string
    {
        return 'Business rule violated';
    }
}

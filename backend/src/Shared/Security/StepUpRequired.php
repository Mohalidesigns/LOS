<?php

declare(strict_types=1);

namespace Fundly\Shared\Security;

use Fundly\Shared\Exceptions\ProblemException;

/** FR-SEC-013: the action needs a recent re-authentication. */
final class StepUpRequired extends ProblemException
{
    public function __construct(int $minutes)
    {
        parent::__construct("Re-authenticate (step-up) within the last {$minutes} minutes to perform this action.", ['max_age_minutes' => $minutes]);
    }

    public function status(): int
    {
        return 401;
    }

    public function type(): string
    {
        return 'step-up-required';
    }

    public function title(): string
    {
        return 'Step-up authentication required';
    }
}

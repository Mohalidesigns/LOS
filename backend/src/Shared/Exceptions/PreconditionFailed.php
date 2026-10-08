<?php

declare(strict_types=1);

namespace Fundly\Shared\Exceptions;

class PreconditionFailed extends ProblemException
{
    public function status(): int
    {
        return 412;
    }

    public function type(): string
    {
        return 'precondition-failed';
    }

    public function title(): string
    {
        return 'Precondition failed';
    }
}

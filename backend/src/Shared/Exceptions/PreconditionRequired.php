<?php

declare(strict_types=1);

namespace Fundly\Shared\Exceptions;

class PreconditionRequired extends ProblemException
{
    public function status(): int
    {
        return 428;
    }

    public function type(): string
    {
        return 'precondition-required';
    }

    public function title(): string
    {
        return 'Precondition required';
    }
}

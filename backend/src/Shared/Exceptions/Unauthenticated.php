<?php

declare(strict_types=1);

namespace Fundly\Shared\Exceptions;

class Unauthenticated extends ProblemException
{
    public function status(): int
    {
        return 401;
    }

    public function type(): string
    {
        return 'unauthenticated';
    }

    public function title(): string
    {
        return 'Authentication required';
    }
}

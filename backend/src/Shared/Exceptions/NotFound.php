<?php

declare(strict_types=1);

namespace Fundly\Shared\Exceptions;

class NotFound extends ProblemException
{
    public function status(): int
    {
        return 404;
    }

    public function type(): string
    {
        return 'not-found';
    }

    public function title(): string
    {
        return 'Resource not found';
    }
}

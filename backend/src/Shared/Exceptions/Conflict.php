<?php

declare(strict_types=1);

namespace Fundly\Shared\Exceptions;

class Conflict extends ProblemException
{
    public function status(): int
    {
        return 409;
    }

    public function type(): string
    {
        return 'conflict';
    }

    public function title(): string
    {
        return 'Conflict';
    }
}

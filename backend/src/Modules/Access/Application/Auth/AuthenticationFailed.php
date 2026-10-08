<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Application\Auth;

use Fundly\Shared\Exceptions\ProblemException;

/** Deliberately generic: never reveals whether the account exists or is locked. */
final class AuthenticationFailed extends ProblemException
{
    public function __construct(string $detail = 'The credentials are invalid or the account cannot sign in at this time.')
    {
        parent::__construct($detail);
    }

    public function status(): int
    {
        return 401;
    }

    public function type(): string
    {
        return 'authentication-failed';
    }

    public function title(): string
    {
        return 'Authentication failed';
    }
}

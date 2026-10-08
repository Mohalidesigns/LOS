<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Application\Auth;

use Fundly\Shared\Exceptions\ProblemException;

final class SessionExpired extends ProblemException
{
    public function __construct(private readonly string $reason)
    {
        parent::__construct('Your session has ended. Sign in again.', ['reason' => $reason]);
    }

    public function status(): int
    {
        return 401;
    }

    public function type(): string
    {
        return $this->reason === 'revoked' ? 'session-revoked' : 'session-expired';
    }

    public function title(): string
    {
        return 'Session ended';
    }
}

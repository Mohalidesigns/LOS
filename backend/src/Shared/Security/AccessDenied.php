<?php

declare(strict_types=1);

namespace Fundly\Shared\Security;

use Fundly\Shared\Exceptions\ProblemException;

final class AccessDenied extends ProblemException
{
    public function __construct(
        public readonly string $permission,
        public readonly string $reason,
        public readonly ?ResourceAttributes $resource = null,
        string $detail = 'You do not have permission to perform this action.',
    ) {
        parent::__construct($detail, ['permission' => $permission]);
    }

    public function status(): int
    {
        return 403;
    }

    public function type(): string
    {
        return $this->reason === 'sod_conflict' ? 'sod-conflict' : 'forbidden';
    }

    public function title(): string
    {
        return 'Forbidden';
    }
}

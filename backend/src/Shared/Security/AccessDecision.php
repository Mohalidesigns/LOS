<?php

declare(strict_types=1);

namespace Fundly\Shared\Security;

final readonly class AccessDecision
{
    private function __construct(
        public bool $allowed,
        public string $permission,
        public ?string $grantingAssignmentId,
        public ?string $onBehalfOf,
        public string $reason,
    ) {
    }

    public static function allow(string $permission, ?string $assignmentId, ?string $onBehalfOf = null, string $reason = 'granted'): self
    {
        return new self(true, $permission, $assignmentId, $onBehalfOf, $reason);
    }

    public static function deny(string $permission, string $reason): self
    {
        return new self(false, $permission, null, null, $reason);
    }
}

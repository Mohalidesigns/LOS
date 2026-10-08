<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Domain;

final readonly class SodRule
{
    public const PERMISSION_PAIR = 'permission_pair';

    public const ROLE_PAIR = 'role_pair';

    public function __construct(
        public string $id,
        public string $kind,
        public string $left,
        public string $right,
        public string $description,
    ) {}

    public function involvesPermission(string $permission): bool
    {
        return $this->kind === self::PERMISSION_PAIR && ($this->left === $permission || $this->right === $permission);
    }

    public function counterpart(string $ref): ?string
    {
        return match ($ref) {
            $this->left => $this->right,
            $this->right => $this->left,
            default => null,
        };
    }
}

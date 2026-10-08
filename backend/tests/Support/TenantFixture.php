<?php

declare(strict_types=1);

namespace Tests\Support;

final class TenantFixture
{
    /** @param list<UserCredentials> $admins */
    public function __construct(
        public readonly string $id,
        public readonly string $slug,
        public readonly string $host,
        public readonly array $admins,
    ) {}

    public function admin(int $i = 0): UserCredentials
    {
        return $this->admins[$i];
    }
}

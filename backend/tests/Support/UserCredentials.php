<?php

declare(strict_types=1);

namespace Tests\Support;

/** A test user with everything needed to sign in through the real flow. */
final class UserCredentials
{
    public ?string $mfaSecret = null;

    public function __construct(
        public readonly string $id,
        public readonly string $email,
        public readonly string $password,
        public readonly string $tenantId,
    ) {
    }
}

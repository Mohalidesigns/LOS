<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Application\Auth;

final readonly class LoginResult
{
    public const MFA_REQUIRED = 'mfa_required';

    public const MFA_ENROLLMENT_REQUIRED = 'mfa_enrollment_required';

    public const AUTHENTICATED = 'authenticated';

    /** @param array<string, string>|null $enrollment */
    private function __construct(public string $status, public ?array $enrollment = null, public ?string $userId = null) {}

    public static function mfaRequired(): self
    {
        return new self(self::MFA_REQUIRED);
    }

    public static function enrollmentRequired(string $secret, string $uri): self
    {
        return new self(self::MFA_ENROLLMENT_REQUIRED, ['secret' => $secret, 'otpauth_uri' => $uri]);
    }

    public static function authenticated(string $userId): self
    {
        return new self(self::AUTHENTICATED, null, $userId);
    }
}

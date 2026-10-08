<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Application\Auth;

use Fundly\Modules\Access\Domain\SessionPolicy;
use Fundly\Modules\Platform\Contracts\ActiveConfiguration;

/**
 * Installation defaults, overridden by the tenant's *active*
 * `security.session_policy` configuration artefact (maker-checker governed).
 */
final class SessionPolicyProvider
{
    public const CONFIG_TYPE = 'security.session_policy';

    public const CONFIG_KEY = 'default';

    public function __construct(private readonly ActiveConfiguration $config) {}

    public function current(): SessionPolicy
    {
        $active = $this->config->content(self::CONFIG_TYPE, self::CONFIG_KEY) ?? [];
        $int = static fn (string $k, int $default): int => isset($active[$k]) && is_int($active[$k]) ? $active[$k] : $default;

        return new SessionPolicy(
            $int('idle_minutes', (int) config('fundly.session.idle_minutes', 15)),
            $int('absolute_minutes', (int) config('fundly.session.absolute_minutes', 480)),
            $int('max_concurrent', (int) config('fundly.session.max_concurrent', 1)),
        );
    }
}

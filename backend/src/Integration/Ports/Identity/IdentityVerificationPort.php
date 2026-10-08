<?php

declare(strict_types=1);

namespace Fundly\Integration\Ports\Identity;

use Fundly\Integration\Ports\Identity\Dto\IdentityCheckRequest;
use Fundly\Integration\Ports\Identity\Dto\IdentityCheckResult;

/**
 * BVN / NIN verification (FR-CUS-002/003, integration register §2). The MVP
 * binds the simulator; NIBSS BVN and NIMC NIN adapters arrive in P4.
 */
interface IdentityVerificationPort
{
    public const PORT = 'identity_verification';

    public const OP_VERIFY = 'verifyIdentity';

    public function verify(IdentityCheckRequest $request): IdentityCheckResult;
}

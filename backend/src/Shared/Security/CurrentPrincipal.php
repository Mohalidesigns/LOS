<?php

declare(strict_types=1);

namespace Fundly\Shared\Security;

use Fundly\Shared\Exceptions\Unauthenticated;

/** Request/job-scoped holder of the acting principal. */
final class CurrentPrincipal
{
    private ?Principal $principal = null;

    public function set(?Principal $principal): void
    {
        $this->principal = $principal;
    }

    public function get(): ?Principal
    {
        return $this->principal;
    }

    public function require(): Principal
    {
        return $this->principal ?? throw new Unauthenticated('Authentication required.');
    }
}

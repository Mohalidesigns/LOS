<?php

declare(strict_types=1);

namespace Fundly\Modules\Platform\Contracts;

/** Read the *active* version of a configuration artefact for the current tenant. */
interface ActiveConfiguration
{
    /** @return array<string, mixed>|null */
    public function content(string $type, string $key): ?array;
}

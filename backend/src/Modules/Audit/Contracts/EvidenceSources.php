<?php

declare(strict_types=1);

namespace Fundly\Modules\Audit\Contracts;

/**
 * Port through which owning modules contribute a section to an application's
 * evidence pack (FR-AUD-010). A module registers one of its own read
 * services; the Audit module never reads another module's tables.
 */
interface EvidenceSources
{
    /**
     * @param  string  $section  file name stem in the pack, e.g. `documents`
     * @param  class-string  $service  resolved from the container
     * @param  string  $method  called as `$method(string $applicationId, Principal $principal)`; returns `['data' => mixed]`
     */
    public function register(string $section, string $service, string $method = 'forApplication'): void;
}

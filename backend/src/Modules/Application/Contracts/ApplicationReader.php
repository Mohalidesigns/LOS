<?php

declare(strict_types=1);

namespace Fundly\Modules\Application\Contracts;

interface ApplicationReader
{
    public function find(string $applicationId): ?ApplicationSummary;
}

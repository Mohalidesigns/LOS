<?php

declare(strict_types=1);

namespace Fundly\Modules\Compliance\Contracts\Events;

use Fundly\Shared\Bus\DomainEvent;

final readonly class ApplicationScreened implements DomainEvent
{
    public function __construct(public string $tenantId, public string $applicationId, public int $hits) {}

    public function name(): string
    {
        return 'compliance.application_screened';
    }

    public function payload(): array
    {
        return ['tenant_id' => $this->tenantId, 'application_id' => $this->applicationId, 'hits' => $this->hits];
    }
}

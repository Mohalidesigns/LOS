<?php

declare(strict_types=1);

namespace Fundly\Modules\Compliance\Contracts\Events;

use Fundly\Shared\Bus\DomainEvent;

final readonly class ScreeningAlertResolved implements DomainEvent
{
    public function __construct(public string $tenantId, public string $alertId, public ?string $applicationId, public string $status) {}

    public function name(): string
    {
        return 'compliance.screening_alert_resolved';
    }

    public function payload(): array
    {
        return ['tenant_id' => $this->tenantId, 'alert_id' => $this->alertId, 'application_id' => $this->applicationId, 'status' => $this->status];
    }
}

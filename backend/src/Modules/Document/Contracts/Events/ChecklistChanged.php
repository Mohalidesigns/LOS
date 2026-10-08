<?php

declare(strict_types=1);

namespace Fundly\Modules\Document\Contracts\Events;

use Fundly\Shared\Bus\DomainEvent;

final readonly class ChecklistChanged implements DomainEvent
{
    public function __construct(public string $tenantId, public string $applicationId, public string $itemCode, public string $status) {}

    public function name(): string
    {
        return 'document.checklist_changed';
    }

    public function payload(): array
    {
        return ['tenant_id' => $this->tenantId, 'application_id' => $this->applicationId, 'item_code' => $this->itemCode, 'status' => $this->status];
    }
}

<?php

declare(strict_types=1);

namespace Fundly\Modules\Approval\Contracts\Events;

use Fundly\Shared\Bus\DomainEvent;

/** A request reached an outcome: approved, declined, counter_offered or expired. */
final readonly class ApprovalDecided implements DomainEvent
{
    public function __construct(
        public string $tenantId,
        public string $applicationId,
        public string $requestId,
        public string $outcome,
        public ?string $validUntil,
    ) {}

    public function name(): string
    {
        return 'approval.decided';
    }

    public function payload(): array
    {
        return ['tenant_id' => $this->tenantId, 'application_id' => $this->applicationId, 'request_id' => $this->requestId, 'outcome' => $this->outcome, 'valid_until' => $this->validUntil];
    }
}

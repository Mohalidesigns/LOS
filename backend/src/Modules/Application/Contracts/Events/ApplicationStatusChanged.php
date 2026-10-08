<?php

declare(strict_types=1);

namespace Fundly\Modules\Application\Contracts\Events;

use Fundly\Shared\Bus\DomainEvent;

/** Published after commit whenever the canonical status moves; other modules react to it (TRD §2.1). */
final readonly class ApplicationStatusChanged implements DomainEvent
{
    public function __construct(
        public string $tenantId,
        public string $applicationId,
        public string $reference,
        public string $from,
        public string $to,
        public ?string $action,
        public ?string $reasonCode,
        public string $actorId,
        public int $version,
    ) {}

    public function name(): string
    {
        return 'application.status_changed';
    }

    public function payload(): array
    {
        return [
            'tenant_id' => $this->tenantId, 'application_id' => $this->applicationId, 'reference' => $this->reference,
            'from' => $this->from, 'to' => $this->to, 'action' => $this->action, 'reason_code' => $this->reasonCode,
            'actor_id' => $this->actorId, 'version' => $this->version,
        ];
    }
}

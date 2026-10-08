<?php

declare(strict_types=1);

namespace Fundly\Modules\Application\Contracts\Events;

use Fundly\Shared\Bus\DomainEvent;

final readonly class ApplicationCreated implements DomainEvent
{
    public function __construct(
        public string $tenantId,
        public string $applicationId,
        public string $reference,
        public string $productVersionId,
        public string $originatorId,
    ) {}

    public function name(): string
    {
        return 'application.created';
    }

    public function payload(): array
    {
        return ['tenant_id' => $this->tenantId, 'application_id' => $this->applicationId, 'reference' => $this->reference, 'product_version_id' => $this->productVersionId, 'originator_id' => $this->originatorId];
    }
}

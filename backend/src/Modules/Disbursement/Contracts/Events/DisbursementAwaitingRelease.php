<?php

declare(strict_types=1);

namespace Fundly\Modules\Disbursement\Contracts\Events;

use Fundly\Shared\Bus\DomainEvent;

/** A maker raised an instruction; a checker must release it. */
final readonly class DisbursementAwaitingRelease implements DomainEvent
{
    public function __construct(public string $tenantId, public string $applicationId, public string $disbursementId, public string $makerId) {}

    public function name(): string
    {
        return 'disbursement.awaiting_release';
    }

    public function payload(): array
    {
        return ['tenant_id' => $this->tenantId, 'application_id' => $this->applicationId, 'disbursement_id' => $this->disbursementId, 'maker_id' => $this->makerId];
    }
}

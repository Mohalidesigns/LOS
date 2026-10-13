<?php

declare(strict_types=1);

namespace Fundly\Modules\Handover\Application;

use Fundly\Integration\Webhooks\Application\WebhookQueries;
use Fundly\Modules\Access\Contracts\AuthorityDirectory;
use Fundly\Modules\Application\Contracts\ApplicationReader;
use Fundly\Modules\Handover\Infrastructure\Models\HandoverAcknowledgement;
use Fundly\Modules\Handover\Infrastructure\Models\HandoverPackage;
use Fundly\Modules\Handover\Infrastructure\Models\RetentionRecord;
use Fundly\Shared\Exceptions\NotFound;
use Fundly\Shared\Security\AuthorizationGate;
use Fundly\Shared\Security\Principal;
use Fundly\Shared\Security\ResourceAttributes;

final class HandoverQueries
{
    public function __construct(
        private readonly ApplicationReader $applications,
        private readonly AuthorizationGate $gate,
        private readonly AuthorityDirectory $directory,
        private readonly WebhookQueries $webhooks,
    ) {}

    /** @return array{data: array<string, mixed>} */
    public function forApplication(string $applicationId, Principal $principal): array
    {
        $this->authorize($applicationId, $principal);
        $p = HandoverPackage::query()->where('application_id', $applicationId)->orderByDesc('created_at')->first();
        $r = RetentionRecord::query()->where('application_id', $applicationId)->where('category', 'origination_record')->first();

        return ['data' => [
            'application_id' => $applicationId,
            'package' => $p === null ? null : $this->package($p),
            'retention' => $r === null ? null : ['category' => $r->category, 'trigger' => $r->trigger, 'starts_on' => $r->starts_on->toDateString(), 'retain_until' => $r->retain_until->toDateString(), 'legal_hold' => $r->legal_hold, 'basis' => $r->basis],
            'deliveries' => $this->webhooks->deliveries('application', $applicationId)['data'],
        ]];
    }

    /** @return array{data: array<string, mixed>} */
    public function show(string $id, Principal $principal): array
    {
        $p = preg_match('/^[0-9a-f-]{36}$/', $id) === 1 ? HandoverPackage::query()->find($id) : null;
        if ($p === null) {
            throw new NotFound('Handover package not found.');
        }
        $this->authorize($p->application_id, $principal);

        return ['data' => $this->package($p)];
    }

    /** @return array<string, mixed> */
    public function package(HandoverPackage $p): array
    {
        $ack = HandoverAcknowledgement::query()->where('package_id', $p->id)->first();
        $names = $this->directory->names(array_values(array_filter([$ack?->acknowledged_by])));

        return [
            'id' => $p->id, 'application_id' => $p->application_id, 'disbursement_id' => $p->disbursement_id, 'sha256' => $p->sha256,
            'servicing_org_unit_id' => $p->servicing_org_unit_id, 'webhook_event_id' => $p->webhook_event_id, 'created_at' => $p->created_at->toIso8601String(),
            'content' => json_decode($p->package_json, true, 64, JSON_THROW_ON_ERROR),
            'acknowledgement' => $ack === null ? null : ['by' => ['id' => $ack->acknowledged_by, 'name' => $names[$ack->acknowledged_by] ?? null], 'at' => $ack->acknowledged_at->toIso8601String(), 'note' => $ack->note],
        ];
    }

    private function authorize(string $applicationId, Principal $principal): void
    {
        $a = $this->applications->find($applicationId) ?? throw new NotFound('Application not found.');
        $this->gate->authorize($principal, 'application:view', new ResourceAttributes(legalEntityId: $a->legalEntityId, orgUnitId: $a->orgUnitId, productId: $a->productId, segment: $a->segment, entityType: 'application', entityId: $a->id));
    }
}

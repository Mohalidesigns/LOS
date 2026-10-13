<?php

declare(strict_types=1);

namespace Fundly\Modules\Offer\Application;

use Fundly\Modules\Access\Contracts\AuthorityDirectory;
use Fundly\Modules\Offer\Infrastructure\Models\Agreement;
use Fundly\Modules\Offer\Infrastructure\Models\Offer;
use Fundly\Modules\Offer\Infrastructure\Models\OfferDelivery;
use Fundly\Modules\Offer\Infrastructure\Models\OfferSignatory;
use Fundly\Shared\Security\AuthorizationGate;
use Fundly\Shared\Security\Principal;

final class OfferQueries
{
    public function __construct(private readonly OfferFile $file, private readonly AuthorizationGate $gate, private readonly AuthorityDirectory $directory) {}

    /** @return array{data: list<array<string, mixed>>} */
    public function forApplication(string $applicationId, Principal $principal): array
    {
        $app = $this->file->application($applicationId);
        $this->gate->authorize($principal, 'application:view', $this->file->resource($app));

        return ['data' => array_values(Offer::query()->where('application_id', $app->id)->orderByDesc('sequence')->get()->map(fn (Offer $o): array => $this->present($o))->values()->all())];
    }

    /** @return array{data: array<string, mixed>} */
    public function show(string $id, Principal $principal): array
    {
        $o = $this->file->offer($id);
        $this->gate->authorize($principal, 'application:view', $this->file->resource($this->file->application($o->application_id)));

        return ['data' => $this->present($o)];
    }

    /** @return array<string, mixed> */
    public function present(Offer $o): array
    {
        $signatories = OfferSignatory::query()->where('offer_id', $o->id)->orderBy('position')->get();
        $deliveries = OfferDelivery::query()->where('offer_id', $o->id)->orderBy('requested_at')->get();
        $agreement = Agreement::query()->where('offer_id', $o->id)->first();
        $names = $this->directory->names(array_values(array_filter(array_merge(
            [$o->issued_by, (string) $o->decided_by],
            $signatories->pluck('recorded_by')->filter()->map(static fn (mixed $v): string => (string) $v)->all(),
            $deliveries->pluck('requested_by')->map(static fn (mixed $v): string => (string) $v)->all(),
        ))));
        $person = static fn (?string $id): ?array => $id === null || $id === '' ? null : ['id' => $id, 'name' => $names[$id] ?? null];
        $next = $signatories->first(static fn (OfferSignatory $s): bool => $s->signed_at === null);
        $now = $this->file->now();

        return [
            'id' => $o->id,
            'application_id' => $o->application_id,
            'sequence' => $o->sequence,
            'status' => $o->status,
            'is_expired' => $o->status === 'issued' && $o->valid_until->lessThan($now),
            'template' => ['key' => $o->template_key, 'version_id' => $o->template_version_id, 'version_no' => $o->template_version_no],
            'execution_method' => $o->execution_method,
            'approval_request_id' => $o->approval_request_id,
            'terms' => $o->terms,
            'key_facts' => $o->key_facts,
            'schedule' => $o->schedule,
            'schedule_source' => $o->schedule_source,
            'document' => ['document_id' => $o->document_id, 'version_id' => $o->document_version_id, 'sha256' => $o->document_sha256],
            'issued_by' => $person($o->issued_by),
            'issued_at' => $o->issued_at->toIso8601String(),
            'valid_until' => $o->valid_until->toIso8601String(),
            'reissue_of' => $o->reissue_of,
            'reissue_count' => $o->reissue_count,
            'decision' => $o->decided_at === null || $o->status === 'issued' ? null : [
                'reason_code' => $o->decision_reason_code, 'note' => $o->decision_note, 'by' => $person($o->decided_by), 'at' => $o->decided_at->toIso8601String(),
            ],
            'signatories' => $signatories->map(static fn (OfferSignatory $s): array => [
                'id' => $s->id, 'position' => $s->position, 'role' => $s->role, 'party_id' => $s->party_id, 'name' => $s->display_name,
                'signed' => $s->signed_at !== null, 'method' => $s->method, 'signed_at' => $s->signed_at?->toIso8601String(),
                'witness_name' => $s->witness_name, 'place' => $s->place, 'recorded_by' => $person($s->recorded_by),
                'is_next' => $next !== null && $next->id === $s->id,
            ])->values()->all(),
            'deliveries' => $deliveries->map(static fn (OfferDelivery $d): array => [
                'id' => $d->id, 'channel' => $d->channel, 'party_id' => $d->party_id, 'recipient' => $d->recipient_hint, 'status' => $d->status,
                'failure' => $d->failure, 'requested_by' => $person($d->requested_by), 'requested_at' => $d->requested_at->toIso8601String(), 'completed_at' => $d->completed_at?->toIso8601String(),
            ])->values()->all(),
            'agreement' => $agreement === null ? null : [
                'id' => $agreement->id, 'document_id' => $agreement->document_id, 'version_id' => $agreement->document_version_id, 'sha256' => $agreement->sha256,
                'offer_document_sha256' => $agreement->offer_document_sha256, 'execution_method' => $agreement->execution_method, 'executed_at' => $agreement->executed_at->toIso8601String(),
            ],
        ];
    }
}

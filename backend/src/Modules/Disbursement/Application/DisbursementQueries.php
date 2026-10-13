<?php

declare(strict_types=1);

namespace Fundly\Modules\Disbursement\Application;

use Fundly\Modules\Access\Contracts\AuthorityDirectory;
use Fundly\Modules\Disbursement\Domain\DisbursementStatus;
use Fundly\Modules\Disbursement\Infrastructure\Models\Disbursement;
use Fundly\Modules\Disbursement\Infrastructure\Models\DisbursementStep;
use Fundly\Modules\Disbursement\Infrastructure\Models\ReconciliationBreak;
use Fundly\Modules\Disbursement\Infrastructure\Models\ReconciliationRun;
use Fundly\Shared\Security\AuthorizationGate;
use Fundly\Shared\Security\Principal;

final class DisbursementQueries
{
    public function __construct(private readonly DisbursementFile $file, private readonly AuthorizationGate $gate, private readonly AuthorityDirectory $directory) {}

    /** @return array{data: list<array<string, mixed>>} */
    public function forApplication(string $applicationId, Principal $principal): array
    {
        $app = $this->file->application($applicationId);
        $this->gate->authorize($principal, 'application:view', $this->file->resource($app, 'application', $app->id));

        return ['data' => array_values(Disbursement::query()->where('application_id', $app->id)->orderByDesc('sequence')->get()->map(fn (Disbursement $d): array => $this->present($d))->all())];
    }

    /**
     * The disbursement exception queue (PR-08).
     *
     * @return array{data: list<array<string, mixed>>}
     */
    public function exceptions(): array
    {
        $statuses = array_map(static fn (DisbursementStatus $s): string => $s->value, array_filter(DisbursementStatus::cases(), static fn (DisbursementStatus $s): bool => $s->isException()));

        return ['data' => array_values(Disbursement::query()->whereIn('status', $statuses)->orderBy('made_at')->get()->map(fn (Disbursement $d): array => $this->present($d))->all())];
    }

    /** @return array{data: list<array<string, mixed>>} */
    public function runs(): array
    {
        return ['data' => array_values(ReconciliationRun::query()->orderByDesc('ran_at')->limit(30)->get()->map(fn (ReconciliationRun $r): array => $this->run($r))->all())];
    }

    /** @return array{data: list<array<string, mixed>>} */
    public function openBreaks(): array
    {
        return ['data' => array_values(ReconciliationBreak::query()->where('status', 'open')->orderBy('id')->get()->map(fn (ReconciliationBreak $b): array => $this->breakRow($b))->all())];
    }

    /** @return array<string, mixed> */
    public function present(Disbursement $d): array
    {
        $steps = DisbursementStep::query()->where('disbursement_id', $d->id)->orderBy('occurred_at')->orderBy('id')->get();
        $names = $this->directory->names(array_values(array_filter([$d->made_by, (string) $d->released_by, (string) $d->cancelled_by])));
        $person = static fn (?string $id): ?array => $id === null || $id === '' ? null : ['id' => $id, 'name' => $names[$id] ?? null];
        $app = $this->file->application($d->application_id);

        return [
            'id' => $d->id, 'application_id' => $d->application_id, 'application_reference' => $app->reference, 'sequence' => $d->sequence, 'status' => $d->status,
            'principal' => ['amount' => $d->principal_amount, 'currency' => $d->currency],
            'net' => ['amount' => $d->net_amount, 'currency' => $d->currency],
            'fees' => $d->fees, 'value_date' => $d->value_date->toDateString(), 'destination' => $d->destination,
            'change_request_id' => $d->change_request_id,
            'made_by' => $person($d->made_by), 'made_at' => $d->made_at->toIso8601String(),
            'released_by' => $person($d->released_by), 'released_at' => $d->released_at?->toIso8601String(),
            'cba' => ['customer_id' => $d->cba_customer_id, 'loan_account_no' => $d->loan_account_no, 'posting_ref' => $d->posting_ref, 'fee_posting_refs' => $d->fee_posting_refs ?? []],
            'variance' => $d->variance,
            'schedule' => $d->cba_schedule ?? [],
            'last_error' => $d->last_error_code === null ? null : ['code' => $d->last_error_code, 'message' => $d->last_error_message],
            'saga_runs' => $d->saga_runs,
            'booked_at' => $d->booked_at?->toIso8601String(),
            'advice' => $d->advice_version_id === null ? null : ['document_id' => $d->advice_document_id, 'version_id' => $d->advice_version_id],
            'cancelled' => $d->cancelled_at === null ? null : ['by' => $person($d->cancelled_by), 'at' => $d->cancelled_at->toIso8601String(), 'reason' => $d->cancel_reason],
            'steps' => array_values($steps->map(static fn (DisbursementStep $s): array => [
                'run' => $s->run, 'step' => $s->step, 'outcome' => $s->outcome, 'detail' => $s->detail, 'error_code' => $s->error_code, 'occurred_at' => $s->occurred_at->toIso8601String(),
            ])->all()),
        ];
    }

    /** @return array<string, mixed> */
    public function run(ReconciliationRun $r): array
    {
        return [
            'id' => $r->id, 'from' => $r->from_date->toDateString(), 'to' => $r->to_date->toDateString(),
            'los_postings' => $r->los_postings, 'cba_postings' => $r->cba_postings, 'matched' => $r->matched, 'breaks' => $r->breaks,
            'ran_by' => $r->ran_by, 'ran_at' => $r->ran_at->toIso8601String(),
            'break_items' => array_values(ReconciliationBreak::query()->where('run_id', $r->id)->orderBy('id')->get()->map(fn (ReconciliationBreak $b): array => $this->breakRow($b))->all()),
        ];
    }

    /** @return array<string, mixed> */
    public function breakRow(ReconciliationBreak $b): array
    {
        return [
            'id' => $b->id, 'run_id' => $b->run_id, 'kind' => $b->kind, 'reference' => $b->reference, 'posting_ref' => $b->posting_ref, 'account_no' => $b->account_no,
            'los_amount' => $b->los_amount, 'cba_amount' => $b->cba_amount, 'currency' => $b->currency, 'disbursement_id' => $b->disbursement_id,
            'status' => $b->status, 'resolved_by' => $b->resolved_by, 'resolved_at' => $b->resolved_at?->toIso8601String(), 'resolution_note' => $b->resolution_note,
        ];
    }
}

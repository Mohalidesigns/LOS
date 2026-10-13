<?php

declare(strict_types=1);

namespace Fundly\Modules\Conditions\Application;

use Fundly\Modules\Access\Contracts\AuthorityDirectory;
use Fundly\Modules\Conditions\Contracts\ConditionsGate;
use Fundly\Modules\Conditions\Infrastructure\Models\Condition;
use Fundly\Modules\Conditions\Infrastructure\Models\PreDisbursementCheck;
use Fundly\Shared\Security\AuthorizationGate;
use Fundly\Shared\Security\Principal;

final class ConditionQueries
{
    public function __construct(private readonly ConditionFile $file, private readonly AuthorizationGate $gate, private readonly AuthorityDirectory $directory, private readonly ConditionsGate $conditions) {}

    /** @return array{data: array<string, mixed>} */
    public function forApplication(string $applicationId, Principal $principal): array
    {
        $app = $this->file->application($applicationId);
        $this->gate->authorize($principal, 'application:view', $this->file->resource($app, 'application', $app->id));
        $conditions = Condition::query()->where('application_id', $app->id)->orderBy('kind')->orderBy('created_at')->get();
        $checks = PreDisbursementCheck::query()->where('application_id', $app->id)->orderByDesc('sequence')->limit(10)->get();
        $blockers = $this->conditions->releaseBlockers($app->id);

        return ['data' => [
            'application_id' => $app->id,
            'status' => $app->status->value,
            'conditions' => array_values($conditions->map(fn (Condition $c): array => $this->condition($c))->all()),
            'checks' => array_values($checks->map(fn (PreDisbursementCheck $k): array => $this->check($k))->all()),
            'readiness' => ['ready_for_release' => $blockers === [], 'blockers' => $blockers],
        ]];
    }

    /** @return array<string, mixed> */
    public function condition(Condition $c): array
    {
        $names = $this->directory->names(array_values(array_filter([(string) $c->owner_id, (string) $c->signed_off_by, $c->created_by])));
        $person = static fn (?string $id): ?array => $id === null || $id === '' ? null : ['id' => $id, 'name' => $names[$id] ?? null];

        return [
            'id' => $c->id, 'application_id' => $c->application_id, 'kind' => $c->kind, 'source' => $c->source, 'description' => $c->description,
            'mandatory' => $c->mandatory, 'owner' => $person($c->owner_id), 'due_date' => $c->due_date?->toDateString(),
            'is_overdue' => $c->due_date !== null && $c->status === 'open' && $c->due_date->lessThan($this->file->now()->startOfDay()),
            'status' => $c->status,
            'evidence' => $c->evidence_version_id === null && $c->evidence_reference === null ? null : ['document_id' => $c->evidence_document_id, 'version_id' => $c->evidence_version_id, 'reference' => $c->evidence_reference],
            'signoff' => $c->signed_off_at === null ? null : ['by' => $person($c->signed_off_by), 'at' => $c->signed_off_at->toIso8601String(), 'note' => $c->signoff_note],
            'waiver' => $c->waiver_change_request_id === null ? null : ['change_request_id' => $c->waiver_change_request_id, 'reason' => $c->waiver_reason],
            'created_by' => $person($c->created_by),
            'created_at' => $c->created_at->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public function check(PreDisbursementCheck $k): array
    {
        $names = $this->directory->names([$k->checked_by]);

        return [
            'id' => $k->id, 'sequence' => $k->sequence, 'passed' => $k->passed, 'items' => $k->items,
            'destination' => ['bank_code' => $k->bank_code, 'account' => $k->account_no_masked, 'account_name' => $k->account_name, 'name_match_score' => $k->name_match_score],
            'screening' => $k->screening,
            'checked_by' => ['id' => $k->checked_by, 'name' => $names[$k->checked_by] ?? null],
            'checked_at' => $k->checked_at->toIso8601String(),
            'valid_until' => $k->valid_until->toIso8601String(),
            'is_current' => $k->valid_until->greaterThan($this->file->now()),
        ];
    }
}

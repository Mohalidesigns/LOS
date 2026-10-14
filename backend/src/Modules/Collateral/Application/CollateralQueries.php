<?php

declare(strict_types=1);

namespace Fundly\Modules\Collateral\Application;

use Fundly\Modules\Access\Contracts\AuthorityDirectory;
use Fundly\Modules\Application\Contracts\ApplicationReader;
use Fundly\Modules\Collateral\Infrastructure\Models\Collateral;
use Fundly\Modules\Collateral\Infrastructure\Models\CollateralAllocation;
use Fundly\Modules\Collateral\Infrastructure\Models\CollateralValuation;
use Fundly\Modules\Collateral\Infrastructure\Models\InsurancePolicy;
use Fundly\Modules\Collateral\Infrastructure\Models\PerfectionStep;
use Fundly\Shared\Money\Money;
use Fundly\Shared\Security\AuthorizationGate;
use Fundly\Shared\Security\Principal;

final class CollateralQueries
{
    /** @var array<string, string>|null */
    private ?array $typeNames = null;

    public function __construct(
        private readonly CollateralFile $file,
        private readonly SecurityAssessor $assessor,
        private readonly AuthorizationGate $gate,
        private readonly AuthorityDirectory $directory,
        private readonly ApplicationReader $applications,
    ) {}

    /** @return array{data: array<string, mixed>} the security file of one application (SCR-APP-07) */
    public function forApplication(string $applicationId, Principal $principal): array
    {
        $app = $this->file->application($applicationId);
        $this->gate->authorize($principal, 'application:view', $this->file->resource($app, 'application', $app->id));
        $allocations = $this->assessor->allocations($app->id);
        $items = $this->assessor->items($allocations);
        $coverage = $this->assessor->coverage($app);
        $security = $this->assessor->security($app);
        $out = [];
        foreach ($allocations as $a) {
            if (isset($items[$a->collateral_id])) {
                $out[] = $this->item($items[$a->collateral_id]) + ['allocated_here' => $a->money()->toArray()];
            }
        }

        return ['data' => [
            'application_id' => $app->id,
            'status' => $app->status->value,
            'exposure' => $this->file->exposure($app)->toArray(),
            'policy' => $this->file->policy($app)->toArray(),
            'cover' => $coverage === null ? null : [
                'lending_value' => $coverage->lendingValue->toArray(), 'market_value' => $coverage->marketValue->toArray(),
                'cover_percent' => $coverage->coverPercent, 'ltv_percent' => $coverage->ltvPercent,
                'meets_cover' => $coverage->meetsCover, 'meets_ltv' => $coverage->meetsLtv,
            ],
            'security' => [
                'applicable' => $security->applicable, 'perfected' => $security->perfectionBlockers === [], 'insurance_in_force' => $security->insuranceBlockers === [],
                'blockers' => array_merge($security->perfectionBlockers, $security->insuranceBlockers), 'open_subsequent_steps' => $security->openSubsequentSteps,
            ],
            'items' => $out,
        ]];
    }

    /** @return array{data: array<string, mixed>} */
    public function show(string $id, Principal $principal): array
    {
        $c = $this->file->collateral($id);
        $app = $this->file->application($c->origin_application_id);
        $this->gate->authorize($principal, 'application:view', $this->file->resource($app, 'collateral', $c->id));

        return ['data' => $this->item($c)];
    }

    /**
     * Items the principal may see, for linking existing collateral to another facility (FR-COL-005).
     *
     * @return array{data: list<array<string, mixed>>}
     */
    public function list(Principal $principal, ?string $ownerPartyId, ?string $applicationId): array
    {
        $q = Collateral::query()->where('status', 'active')->orderByDesc('created_at')->limit(100);
        if ($ownerPartyId !== null) {
            $q->where('owner_party_id', $ownerPartyId);
        }
        if ($applicationId !== null) {
            $q->whereIn('id', CollateralAllocation::query()->where('application_id', $applicationId)->where('status', 'active')->select('collateral_id'));
        }
        $out = [];
        foreach ($q->get() as $c) {
            $app = $this->applications->find($c->origin_application_id);
            if ($app !== null && $this->gate->check($principal, 'application:view', $this->file->resource($app, 'collateral', $c->id))->allowed) {
                $out[] = $this->item($c);
            }
        }

        return ['data' => $out];
    }

    /** @return array{data: list<array<string, mixed>>} */
    public function types(): array
    {
        $out = [];
        foreach ($this->file->types() as $code => $t) {
            $type = $t['type'];
            $out[] = [
                'code' => $code, 'name' => $type->name(), 'category' => $type->category(), 'haircut_percent' => (string) $type->haircutPercent(),
                'requires_valuation' => $type->requiresValuation(), 'valuation_validity_months' => $type->valuationValidityMonths(), 'requires_insurance' => $type->requiresInsurance(),
                'perfection_steps' => $type->perfectionSteps(null), 'source' => $t['source'], 'version_id' => $t['version_id'],
            ];
        }

        return ['data' => $out];
    }

    /** @return array<string, mixed> */
    public function item(Collateral $c): array
    {
        $allocations = CollateralAllocation::query()->where('collateral_id', $c->id)->orderBy('created_at')->get();
        $total = $this->assessor->allocatedTotal($c);
        $fsv = $c->forcedSale();
        $available = $total->isGreaterThan($fsv) ? Money::zero($c->currency) : $fsv->minus($total);
        $valuations = CollateralValuation::query()->where('collateral_id', $c->id)->orderByDesc('sequence')->get();
        $steps = PerfectionStep::query()->where('collateral_id', $c->id)->orderBy('sequence')->get();
        $policies = InsurancePolicy::query()->where('collateral_id', $c->id)->orderByDesc('expiry_date')->get();
        [$insuranceStatus, $current] = $this->assessor->insurance($c);
        $people = [$c->created_by];
        foreach ($steps as $s) {
            $people[] = (string) $s->responsible_user_id;
            $people[] = (string) $s->completed_by;
        }
        foreach ($valuations as $v) {
            $people[] = $v->recorded_by;
        }
        foreach ($policies as $p) {
            $people[] = $p->recorded_by;
        }
        $names = $this->directory->names(array_values(array_unique(array_filter($people))));
        $person = static fn (?string $id): ?array => $id === null || $id === '' ? null : ['id' => $id, 'name' => $names[$id] ?? null];
        $references = [];
        foreach ($allocations as $a) {
            $references[$a->application_id] ??= $this->applications->find($a->application_id)?->reference;
        }
        $today = $this->file->today();

        return [
            'id' => $c->id,
            'origin_application_id' => $c->origin_application_id,
            'type' => ['code' => $c->type_code, 'name' => $this->typeName($c->type_code), 'category' => $c->category, 'source' => $c->type_source, 'version_id' => $c->type_version_id],
            'description' => $c->description,
            'owner' => ['party_id' => $c->owner_party_id, 'name' => $c->owner_name],
            'state_code' => $c->state_code,
            'market_value' => $c->market()->toArray(),
            'haircut_percent' => $c->haircut_percent,
            'forced_sale_value' => $fsv->toArray(),
            'value_basis' => $c->value_basis,
            'status' => $c->status,
            'allocation' => [
                'total' => $total->toArray(), 'available' => $available->toArray(), 'over_allocated' => $total->isGreaterThan($fsv),
                'items' => array_values($allocations->map(static fn (CollateralAllocation $a): array => [
                    'id' => $a->id, 'application_id' => $a->application_id, 'application_reference' => $references[$a->application_id] ?? null, 'amount' => $a->money()->toArray(), 'status' => $a->status,
                ])->all()),
            ],
            'valuation' => [
                'required' => $c->requires_valuation, 'status' => $this->assessor->valuationStatus($c), 'valid_until' => $c->valuation_valid_until?->toDateString(),
                'history' => array_values($valuations->map(static fn (CollateralValuation $v): array => [
                    'id' => $v->id, 'sequence' => $v->sequence, 'valuer' => $v->valuer, 'valuation_date' => $v->valuation_date->toDateString(), 'method' => $v->method,
                    'market_value' => Money::of($v->market_value, $v->currency)->toArray(), 'forced_sale_value' => Money::of($v->forced_sale_value, $v->currency)->toArray(),
                    'valid_until' => $v->valid_until?->toDateString(),
                    'report' => $v->report_version_id === null ? null : ['document_id' => $v->report_document_id, 'version_id' => $v->report_version_id],
                    'note' => $v->note, 'recorded_by' => $person($v->recorded_by), 'recorded_at' => $v->recorded_at->toIso8601String(),
                ])->all()),
            ],
            'perfection' => [
                'precedent_complete' => $steps->every(static fn (PerfectionStep $s): bool => $s->timing !== 'precedent' || ! in_array($s->status, PerfectionStep::OPEN, true)),
                'steps' => array_values($steps->map(static fn (PerfectionStep $s): array => [
                    'id' => $s->id, 'sequence' => $s->sequence, 'code' => $s->code, 'name' => $s->name, 'timing' => $s->timing,
                    'responsible' => $person($s->responsible_user_id), 'due_date' => $s->due_date?->toDateString(),
                    'is_overdue' => $s->due_date !== null && in_array($s->status, PerfectionStep::OPEN, true) && $s->due_date->lessThan($today),
                    'status' => $s->status,
                    'evidence' => $s->evidence_version_id === null && $s->evidence_reference === null ? null : ['document_id' => $s->evidence_document_id, 'version_id' => $s->evidence_version_id, 'reference' => $s->evidence_reference],
                    'completion' => $s->completed_at === null ? null : ['by' => $person($s->completed_by), 'at' => $s->completed_at->toIso8601String(), 'note' => $s->note],
                    'waiver' => $s->waiver_change_request_id === null ? null : ['change_request_id' => $s->waiver_change_request_id, 'reason' => $s->waiver_reason],
                ])->all()),
            ],
            'insurance' => [
                'requirement' => $c->insurance_requirement, 'status' => $insuranceStatus, 'current_policy_id' => $current?->id,
                'waiver' => $c->insurance_waiver_change_request_id === null ? null : ['change_request_id' => $c->insurance_waiver_change_request_id, 'reason' => $c->insurance_waiver_reason],
                'policies' => array_values($policies->map(static fn (InsurancePolicy $p): array => [
                    'id' => $p->id, 'policy_no' => $p->policy_no, 'insurer' => $p->insurer, 'sum_insured' => Money::of($p->sum_insured, $p->currency)->toArray(),
                    'start_date' => $p->start_date->toDateString(), 'expiry_date' => $p->expiry_date->toDateString(), 'bank_interest_noted' => $p->bank_interest_noted,
                    'under_insured' => Money::of($p->sum_insured, $p->currency)->compareTo($c->market()) < 0,
                    'document' => $p->document_version_id === null ? null : ['document_id' => $p->document_id, 'version_id' => $p->document_version_id],
                    'recorded_by' => $person($p->recorded_by), 'recorded_at' => $p->recorded_at->toIso8601String(),
                ])->all()),
            ],
            'created_by' => $person($c->created_by),
            'created_at' => $c->created_at->toIso8601String(),
        ];
    }

    private function typeName(string $code): string
    {
        if ($this->typeNames === null) {
            $this->typeNames = array_map(static fn (array $t): string => $t['type']->name(), $this->file->types());
        }

        return $this->typeNames[$code] ?? $code;
    }
}

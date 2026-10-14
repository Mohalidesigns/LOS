<?php

declare(strict_types=1);

namespace Fundly\Modules\Approval\Application;

use Fundly\Modules\Access\Contracts\AuthorityDirectory;
use Fundly\Modules\Application\Contracts\ApplicationSummary;
use Fundly\Modules\Approval\Infrastructure\Models\ApprovalCondition;
use Fundly\Modules\Approval\Infrastructure\Models\ApprovalRequest;
use Fundly\Modules\Approval\Infrastructure\Models\ApprovalVote;
use Fundly\Modules\Collateral\Contracts\CollateralRegister;
use Fundly\Modules\Collateral\Contracts\SecuredItem;
use Fundly\Modules\Credit\Contracts\CreditFile;
use Fundly\Shared\Money\Money;
use Fundly\Shared\Security\AuthorizationGate;
use Fundly\Shared\Security\Principal;

final class ApprovalQueries
{
    public function __construct(
        private readonly ApprovalFile $file,
        private readonly AuthorizationGate $gate,
        private readonly AuthorityDirectory $directory,
        private readonly CreditFile $credit,
        private readonly CollateralRegister $collateral,
    ) {}

    /** @return array{data: list<array<string, mixed>>} */
    public function forApplication(string $applicationId, Principal $principal): array
    {
        $app = $this->authorized($applicationId, $principal);
        $out = [];
        foreach (ApprovalRequest::query()->where('application_id', $app->id)->orderByDesc('sequence')->get() as $r) {
            $out[] = $this->present($r, $app, $principal);
        }

        return ['data' => $out];
    }

    /**
     * One request with the full decision packet (FR-APV-010).
     *
     * @return array{data: array<string, mixed>}
     */
    public function show(string $id, Principal $principal): array
    {
        $r = $this->file->request($id);
        $app = $this->authorized($r->application_id, $principal);

        return ['data' => $this->present($r, $app, $principal) + ['packet' => $this->packet($r, $app)]];
    }

    /**
     * Pending requests whose current level the principal holds a role for.
     *
     * @return array{data: list<array<string, mixed>>}
     */
    public function inbox(Principal $principal): array
    {
        $out = [];
        foreach (ApprovalRequest::query()->where('status', 'pending')->orderBy('opened_at')->get() as $r) {
            $app = $this->file->application($r->application_id);
            $roles = $r->levels[$r->current_level]['roles'] ?? [];
            $mine = array_filter($this->file->authorities($principal->id, $app, $this->file->limits($r->exposure_currency)), static fn (array $a): bool => in_array($a['role_code'], $roles, true));
            if ($mine !== []) {
                $out[] = $this->present($r, $app, $principal);
            }
        }

        return ['data' => $out];
    }

    /** @return array<string, mixed> */
    public function present(ApprovalRequest $r, ApplicationSummary $app, Principal $principal): array
    {
        $votes = ApprovalVote::query()->where('request_id', $r->id)->orderBy('level_index')->get();
        $names = $this->directory->names(array_values(array_merge([$r->recommended_by, $app->originatorId], $votes->pluck('voter_id')->map(static fn (mixed $v): string => (string) $v)->all())));
        $byLevel = $votes->keyBy('level_index');
        $levels = [];
        foreach ($r->levels as $i => $l) {
            $vote = $byLevel[$i] ?? null;
            $levels[] = [
                'index' => $i, 'code' => $l['code'], 'name' => $l['name'], 'roles' => $l['roles'], 'mode' => $l['mode'],
                'added_by' => $l['added_by'], 'note' => $l['note'],
                'state' => $vote instanceof ApprovalVote ? $vote->decision : ($r->status === 'pending' && $i === $r->current_level ? 'awaiting_vote' : ($r->status === 'pending' && $i > $r->current_level ? 'queued' : 'not_reached')),
            ];
        }
        $check = $r->status === 'pending' ? $this->file->eligibility($principal->id, $r, $app) : ['eligible' => false, 'blockers' => [], 'basis' => null];
        $exposure = Money::of($r->exposure_amount, $r->exposure_currency);

        return [
            'id' => $r->id,
            'application_id' => $app->id,
            'application_reference' => $app->reference,
            'sequence' => $r->sequence,
            'status' => $r->status,
            'matrix' => ['key' => $r->matrix_key, 'version_id' => $r->matrix_version_id, 'version_no' => $r->matrix_version_no, 'row' => $r->matrix_row],
            'levels' => $levels,
            'current_level' => $r->status === 'pending' ? $r->current_level : null,
            'exposure' => $exposure->toArray(),
            'decision_id' => $r->decision_id,
            'originator' => ['id' => $app->originatorId, 'name' => $names[$app->originatorId] ?? null],
            'recommended_by' => ['id' => $r->recommended_by, 'name' => $names[$r->recommended_by] ?? null],
            'opened_at' => $r->opened_at->toIso8601String(),
            'decided_at' => $r->decided_at?->toIso8601String(),
            'validity_days' => $r->validity_days,
            'valid_until' => $r->valid_until?->toIso8601String(),
            'counter_terms' => $r->counter_terms,
            'votes' => $votes->map(fn (ApprovalVote $v): array => self::vote($v, $names[$v->voter_id] ?? null))->values()->all(),
            'conditions' => ApprovalCondition::query()->where('request_id', $r->id)->orderBy('created_at')->orderBy('id')->get()->map(static fn (ApprovalCondition $c): array => [
                'id' => $c->id, 'kind' => $c->kind, 'description' => $c->description, 'status' => $c->status, 'vote_id' => $c->vote_id,
            ])->values()->all(),
            'facts' => $r->facts,
            'routing_trace' => $r->routing_trace,
            'my_eligibility' => [
                'can_vote' => $check['eligible'],
                'blockers' => $check['blockers'],
                'basis' => $check['basis'],
                'step_up_required' => $r->status === 'pending' && $this->file->matrixOf($r)->needsStepUp($exposure->amount),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private static function vote(ApprovalVote $v, ?string $name): array
    {
        return [
            'id' => $v->id,
            'level_index' => $v->level_index,
            'level_code' => $v->level_code,
            'voter' => ['id' => $v->voter_id, 'name' => $name],
            'decision' => $v->decision,
            'rationale' => $v->rationale,
            'reason_codes' => $v->reason_codes,
            'conditions' => $v->conditions,
            'counter_terms' => $v->counter_terms,
            'authority_basis' => $v->authority_basis,
            'stepped_up' => $v->step_up_ref !== null,
            'step_up_ref' => $v->step_up_ref,
            'voted_at' => $v->voted_at->toIso8601String(),
        ];
    }

    /**
     * What the approver needs in one place (FR-APV-010): the application, the
     * credit decision with exceptions and memo recommendation, bureau
     * currency, and every earlier approval request on this application.
     *
     * @return array<string, mixed>
     */
    private function packet(ApprovalRequest $r, ApplicationSummary $app): array
    {
        $product = $this->file->product($app);
        $d = $this->credit->latestDecision($app->id);
        $history = [];
        foreach (ApprovalRequest::query()->where('application_id', $app->id)->where('id', '!=', $r->id)->orderByDesc('sequence')->get() as $prior) {
            $history[] = ['id' => $prior->id, 'sequence' => $prior->sequence, 'status' => $prior->status, 'opened_at' => $prior->opened_at->toIso8601String(), 'decided_at' => $prior->decided_at?->toIso8601String(),
                'votes' => ApprovalVote::query()->where('request_id', $prior->id)->orderBy('level_index')->get()->map(static fn (ApprovalVote $v): array => ['level_code' => $v->level_code, 'decision' => $v->decision, 'voter_id' => $v->voter_id, 'voted_at' => $v->voted_at->toIso8601String()])->values()->all()];
        }

        return [
            'application' => [
                'id' => $app->id, 'reference' => $app->reference, 'status' => $app->status->value,
                'product' => ['key' => $product->key, 'name' => $product->name, 'version_no' => $product->versionNo],
                'segment' => $app->segment, 'channel' => $app->channel,
                'requested_amount' => $app->requestedAmount?->toArray(), 'tenor_months' => $app->tenorMonths, 'purpose' => is_string($app->data['purpose'] ?? null) ? $app->data['purpose'] : null,
                'applicant_count' => count($app->applicants),
            ],
            'credit' => $d === null ? null : [
                'decision_id' => $d->decisionId, 'outcome' => $d->outcome, 'risk_grade' => $d->riskGrade,
                'recommended_terms' => ['amount' => $d->recommendedAmount->toArray(), 'tenor_months' => $d->tenorMonths, 'rate_percent' => $d->ratePercent],
                'exception_count' => $d->exceptionCount, 'max_exception_severity' => $d->maxExceptionSeverity, 'memo_recommendation' => $d->memoRecommendation,
                'is_routed_decision' => $d->decisionId === $r->decision_id,
            ],
            'bureau_valid' => $this->credit->hasValidBureauReport($app->id),
            'collateral' => $this->collateralPacket($app->id),
            'history' => $history,
        ];
    }

    /** @return array<string, mixed> cover against policy and each item with its perfection status (FR-APV-010) */
    private function collateralPacket(string $applicationId): array
    {
        $cover = $this->collateral->coverage($applicationId);
        $security = $this->collateral->security($applicationId);

        return [
            'cover_percent' => $cover?->coverPercent, 'ltv_percent' => $cover?->ltvPercent,
            'min_cover_percent' => $cover?->minCoverPercent, 'max_ltv_percent' => $cover?->maxLtvPercent,
            'meets_cover' => $cover?->meetsCover, 'meets_ltv' => $cover?->meetsLtv,
            'lending_value' => $cover?->lendingValue->toArray(), 'outstanding_perfection' => count($security->perfectionBlockers),
            'items' => array_map(static fn (SecuredItem $i): array => [
                'collateral_id' => $i->collateralId, 'type_code' => $i->typeCode, 'description' => $i->description, 'owner_name' => $i->ownerName,
                'market_value' => $i->marketValue->toArray(), 'forced_sale_value' => $i->forcedSaleValue->toArray(), 'allocated' => $i->allocated->toArray(),
                'open_steps' => count($i->openSteps),
            ], $this->collateral->securedItems($applicationId)),
        ];
    }

    private function authorized(string $applicationId, Principal $principal): ApplicationSummary
    {
        $app = $this->file->application($applicationId);
        $this->gate->authorize($principal, 'application:view', $this->file->resource($app));

        return $app;
    }
}

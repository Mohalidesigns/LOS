<?php

declare(strict_types=1);

namespace Fundly\Modules\Approval\Application\Commands;

use DateInterval;
use Fundly\Modules\Application\Contracts\ApplicationLifecycle;
use Fundly\Modules\Application\Contracts\CanonicalStatus;
use Fundly\Modules\Approval\Application\ApprovalFile;
use Fundly\Modules\Approval\Application\ApprovalQueries;
use Fundly\Modules\Approval\Contracts\Events\ApprovalDecided;
use Fundly\Modules\Approval\Infrastructure\Models\ApprovalCondition;
use Fundly\Modules\Approval\Infrastructure\Models\ApprovalRequest;
use Fundly\Modules\Approval\Infrastructure\Models\ApprovalVote;
use Fundly\Modules\Compliance\Contracts\ComplianceGate;
use Fundly\Modules\Credit\Contracts\CreditFile;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Exceptions\DomainRuleViolation;
use Fundly\Shared\Exceptions\ValidationFailed;
use Fundly\Shared\Money\Money;
use Fundly\Shared\Security\AccessDenied;
use Fundly\Shared\Security\StepUpRequired;

final class CastApprovalVoteHandler implements CommandHandler
{
    public function __construct(
        private readonly ApprovalFile $file,
        private readonly CreditFile $credit,
        private readonly ApplicationLifecycle $lifecycle,
        private readonly ApprovalQueries $queries,
        private readonly ComplianceGate $compliance,
    ) {}

    /** @return array{data: array<string, mixed>} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof CastApprovalVote);
        $r = ApprovalRequest::query()->lockForUpdate()->findOrFail($this->file->request($command->requestId)->id);
        $app = $this->file->application($r->application_id);
        if ($r->status !== 'pending') {
            throw new DomainRuleViolation("This approval request is {$r->status}; votes are closed.");
        }
        if ($app->status !== CanonicalStatus::Approval) {
            throw new DomainRuleViolation("The application is {$app->status->value}, not in approval.");
        }
        $principal = $context->principal;
        $check = $this->file->eligibility($principal->id, $r, $app);
        if (! $check['eligible']) {
            $sod = array_filter($check['blockers'], static fn (string $b): bool => str_contains($b, 'FR-APV-005')) !== [];
            throw new AccessDenied($command->permission(), $sod ? 'approver_sod' : 'approval_authority', $this->file->resource($app), implode(' ', $check['blockers']));
        }
        $exposure = Money::of($r->exposure_amount, $r->exposure_currency);
        $matrix = $this->file->matrixOf($r);
        if ($matrix->needsStepUp($exposure->amount) && ($principal->viaToken() || ! $principal->steppedUpWithin($this->file->stepUpMinutes(), $this->file->now()->toDateTimeImmutable()))) {
            throw new StepUpRequired($this->file->stepUpMinutes());
        }
        if ($command->decision !== 'decline' && ! $this->credit->hasValidBureauReport($app->id)) {
            throw new DomainRuleViolation('The credit bureau report has lapsed; pull a current report before approving (FR-CMP-020).');
        }
        // FR-CMP-023: a facility that would breach the single-obligor limit cannot be approved here
        if (in_array($command->decision, ['approve', 'approve_with_conditions'], true)) {
            $limits = $this->compliance->regulatoryLimits($app->id, 'approval', $principal->id);
            if ($limits->blocks()) {
                throw new DomainRuleViolation("{$limits->detail} (FR-CMP-023)");
            }
        }
        $counter = null;
        if ($command->decision === 'counter_offer') {
            $terms = $command->counterTerms ?? [];
            $amount = Money::of((string) ($terms['amount'] ?? '0'), $exposure->currency);
            if (! $exposure->isGreaterThan($amount) || $amount->isZero()) {
                throw ValidationFailed::with(['counter_terms.amount' => 'A counter-offer must be for less than the requested amount.']);
            }
            $counter = ['amount' => $amount->toArray(), 'tenor_months' => (int) ($terms['tenor_months'] ?? 0)];
        }

        $level = $r->levels[$r->current_level];
        $basis = $check['basis'] + [
            'level' => ['index' => $r->current_level, 'code' => $level['code'], 'name' => $level['name'], 'added_by' => $level['added_by']],
            'matrix' => ['key' => $r->matrix_key, 'version_no' => $r->matrix_version_no, 'row' => $r->matrix_row],
            'exposure' => $exposure->toArray(),
        ];
        $vote = new ApprovalVote;
        $vote->forceFill([
            'request_id' => $r->id,
            'application_id' => $app->id,
            'level_index' => $r->current_level,
            'level_code' => $level['code'],
            'voter_id' => $principal->id,
            'decision' => $command->decision,
            'rationale' => trim((string) $command->rationale),
            'reason_codes' => array_values(array_map('strval', $command->reasonCodes)),
            'conditions' => array_map(static fn (mixed $c): array => ['kind' => (string) $c['kind'], 'description' => trim((string) $c['description'])], $command->conditions),
            'counter_terms' => $counter,
            'authority_basis' => $basis,
            'step_up_ref' => $principal->stepUpRef,
            'voted_at' => $this->file->now(),
        ])->save();
        foreach ($vote->conditions as $c) {
            (new ApprovalCondition)->forceFill([
                'application_id' => $app->id, 'request_id' => $r->id, 'vote_id' => $vote->id,
                'kind' => $c['kind'], 'description' => $c['description'], 'status' => 'open', 'created_at' => $vote->voted_at,
            ])->save();
        }

        $approving = in_array($command->decision, ['approve', 'approve_with_conditions'], true);
        $last = $r->current_level >= count($r->levels) - 1;
        [$to, $code, $note] = match (true) {
            $approving && ! $last => [CanonicalStatus::Approval, 'APPROVAL_LEVEL_PASSED', "{$level['code']} approved; awaiting {$r->levels[$r->current_level + 1]['code']}."],
            $approving => [CanonicalStatus::Approved, 'APPROVED', "Approved at {$level['code']}."],
            $command->decision === 'decline' => [CanonicalStatus::Declined, 'APPROVAL_DECLINED', 'Declined at '.$level['code'].': '.implode(', ', $vote->reason_codes)],
            default => [CanonicalStatus::CounterOffered, 'APPROVAL_COUNTER_OFFER', 'Counter-offer at '.$level['code'].': '.implode(', ', $vote->reason_codes)],
        };
        $before = ['status' => $r->status, 'current_level' => $r->current_level];
        if ($approving && ! $last) {
            $r->current_level++;
        } else {
            $r->status = match ($to) {
                CanonicalStatus::Approved => 'approved',
                CanonicalStatus::Declined => 'declined',
                default => 'counter_offered',
            };
            $r->decided_at = $vote->voted_at;
            $r->counter_terms = $counter;
            if ($to === CanonicalStatus::Approved) {
                $r->valid_until = $vote->voted_at->copy()->add(new DateInterval("P{$r->validity_days}D"));
            }
        }
        $r->save();

        $context->audit(new AuditEntry(
            action: $command->action(),
            entityType: 'approval_request',
            entityId: $r->id,
            before: $before,
            after: ['vote_id' => $vote->id, 'decision' => $vote->decision, 'level' => $level['code'], 'reason_codes' => $vote->reason_codes, 'conditions' => count($vote->conditions), 'authority_basis' => $basis, 'status' => $r->status, 'current_level' => $r->current_level],
            reasonCode: $vote->reason_codes[0] ?? null,
            reasonText: $vote->rationale,
        ));
        $this->lifecycle->advance($app->id, $to, $code, $note, $principal);
        if ($r->status !== 'pending') {
            $context->raise(new ApprovalDecided($principal->tenantId, $app->id, $r->id, $r->status, $r->valid_until?->toIso8601String()));
        }

        return ['data' => $this->queries->present($r, $app, $principal)];
    }
}

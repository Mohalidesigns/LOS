<?php

declare(strict_types=1);

namespace Fundly\Modules\Disbursement\Application;

use Fundly\Modules\Application\Contracts\ApplicationReader;
use Fundly\Modules\Application\Contracts\ApplicationSummary;
use Fundly\Modules\Approval\Contracts\ApprovalRegister;
use Fundly\Modules\Disbursement\Infrastructure\Models\Disbursement;
use Fundly\Modules\Disbursement\Infrastructure\Models\DisbursementStep;
use Fundly\Shared\Clock\Clock;
use Fundly\Shared\Exceptions\NotFound;
use Fundly\Shared\Security\ResourceAttributes;
use Illuminate\Support\Carbon;

/** Shared lookups, release preconditions and the step log. */
final class DisbursementFile
{
    public function __construct(private readonly ApplicationReader $applications, private readonly ApprovalRegister $approvals, private readonly Clock $clock) {}

    public function application(string $id): ApplicationSummary
    {
        return $this->applications->find($id) ?? throw new NotFound('Application not found.');
    }

    public function disbursement(string $id): Disbursement
    {
        $d = preg_match('/^[0-9a-f-]{36}$/', $id) === 1 ? Disbursement::query()->find($id) : null;

        return $d ?? throw new NotFound('Disbursement not found.');
    }

    /** Approval still stands at release (FR-APV-012: re-checked at release and at the saga's first step). */
    public function approvalBlocker(string $applicationId): ?string
    {
        $a = $this->approvals->current($applicationId);
        if ($a === null) {
            return 'No approval decision is on file.';
        }
        if ($a->status === 'counter_offered') {
            return null;
        }

        return $a->isValidAt($this->now()->toDateTimeImmutable()) ? null : "The approval is {$a->status} or past its validity; re-approval is required (FR-APV-012).";
    }

    /** @return list<string> */
    public function approverIds(string $applicationId): array
    {
        return $this->approvals->current($applicationId)->voterIds ?? [];
    }

    /** @param array<string, mixed> $detail */
    public function log(Disbursement $d, string $step, string $outcome, array $detail = [], ?string $errorCode = null): void
    {
        (new DisbursementStep)->forceFill([
            'disbursement_id' => $d->id, 'run' => $d->saga_runs, 'step' => $step, 'outcome' => $outcome,
            'detail' => $detail, 'error_code' => $errorCode, 'occurred_at' => $this->now(),
        ])->save();
    }

    public function resource(ApplicationSummary $app, string $type, string $id): ResourceAttributes
    {
        return new ResourceAttributes(legalEntityId: $app->legalEntityId, orgUnitId: $app->orgUnitId, productId: $app->productId, segment: $app->segment, entityType: $type, entityId: $id);
    }

    public function now(): Carbon
    {
        return Carbon::instance($this->clock->now());
    }
}

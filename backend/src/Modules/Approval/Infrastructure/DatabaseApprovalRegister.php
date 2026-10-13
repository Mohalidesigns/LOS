<?php

declare(strict_types=1);

namespace Fundly\Modules\Approval\Infrastructure;

use Fundly\Modules\Approval\Contracts\ApprovalConditionView;
use Fundly\Modules\Approval\Contracts\ApprovalRegister;
use Fundly\Modules\Approval\Contracts\ApprovalView;
use Fundly\Modules\Approval\Infrastructure\Models\ApprovalCondition;
use Fundly\Modules\Approval\Infrastructure\Models\ApprovalRequest;
use Fundly\Modules\Approval\Infrastructure\Models\ApprovalVote;
use Fundly\Shared\Money\Money;

final class DatabaseApprovalRegister implements ApprovalRegister
{
    public function current(string $applicationId): ?ApprovalView
    {
        $r = ApprovalRequest::query()->where('application_id', $applicationId)->where('status', '!=', 'superseded')->orderByDesc('sequence')->first();
        if ($r === null) {
            return null;
        }
        $levels = ApprovalVote::query()->where('request_id', $r->id)->pluck('level_code', 'id');
        $conditions = [];
        foreach (ApprovalCondition::query()->where('request_id', $r->id)->orderBy('created_at')->orderBy('id')->get() as $c) {
            $conditions[] = new ApprovalConditionView($c->id, $c->kind, $c->description, $c->status, (string) ($levels[$c->vote_id] ?? ''));
        }
        $counter = $r->counter_terms;

        return new ApprovalView(
            requestId: $r->id,
            status: $r->status,
            exposure: Money::of($r->exposure_amount, $r->exposure_currency),
            decidedAt: $r->decided_at?->toDateTimeImmutable(),
            validUntil: $r->valid_until?->toDateTimeImmutable(),
            counterAmount: is_array($counter['amount'] ?? null) ? Money::fromArray($counter['amount']) : null,
            counterTenorMonths: is_int($counter['tenor_months'] ?? null) ? $counter['tenor_months'] : null,
            conditions: $conditions,
        );
    }
}

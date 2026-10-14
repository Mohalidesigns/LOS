<?php

declare(strict_types=1);

namespace Fundly\Modules\Collateral\Infrastructure;

use DateTimeImmutable;
use Fundly\Modules\Collateral\Application\CollateralFile;
use Fundly\Modules\Collateral\Application\SecurityAssessor;
use Fundly\Modules\Collateral\Contracts\CollateralAttention;
use Fundly\Modules\Collateral\Contracts\CollateralCoverage;
use Fundly\Modules\Collateral\Contracts\CollateralRegister;
use Fundly\Modules\Collateral\Contracts\SecuredItem;
use Fundly\Modules\Collateral\Contracts\SecurityPosition;
use Fundly\Modules\Collateral\Infrastructure\Models\Collateral;
use Fundly\Modules\Collateral\Infrastructure\Models\CollateralAllocation;
use Fundly\Modules\Collateral\Infrastructure\Models\PerfectionStep;
use Fundly\Shared\Money\Money;

final class DatabaseCollateralRegister implements CollateralRegister
{
    public function __construct(private readonly CollateralFile $file, private readonly SecurityAssessor $assessor) {}

    public function coverage(string $applicationId): ?CollateralCoverage
    {
        return $this->assessor->coverage($this->file->application($applicationId));
    }

    public function security(string $applicationId): SecurityPosition
    {
        return $this->assessor->security($this->file->application($applicationId));
    }

    public function securedItems(string $applicationId): array
    {
        $allocations = $this->assessor->allocations($applicationId);
        $items = $this->assessor->items($allocations);
        $out = [];
        foreach ($allocations as $a) {
            $c = $items[$a->collateral_id] ?? null;
            if ($c === null) {
                continue;
            }
            $steps = PerfectionStep::query()->where('collateral_id', $c->id)->whereIn('status', PerfectionStep::OPEN)->orderBy('sequence')->get();
            [, $policy] = $this->assessor->insurance($c);
            $out[] = new SecuredItem(
                $c->id, $c->type_code, $c->category, $c->description, $c->owner_party_id, $c->owner_name,
                $a->money(), $c->market(), $c->forcedSale(), $c->valuation_valid_until?->toDateString(),
                array_values($steps->map(static fn (PerfectionStep $s): array => [
                    'step_id' => $s->id, 'code' => $s->code, 'name' => $s->name, 'timing' => $s->timing, 'status' => $s->status,
                    'responsible_id' => $s->responsible_user_id, 'due_date' => $s->due_date?->toDateString(),
                ])->all()),
                $policy === null ? null : [
                    'policy_no' => $policy->policy_no, 'insurer' => $policy->insurer, 'sum_insured' => Money::of($policy->sum_insured, $policy->currency)->toArray(),
                    'expiry_date' => $policy->expiry_date->toDateString(), 'bank_interest_noted' => $policy->bank_interest_noted,
                ],
            );
        }

        return $out;
    }

    public function attentionDue(DateTimeImmutable $until): array
    {
        $out = [];
        $live = CollateralAllocation::query()->where('status', 'active')->select('collateral_id');
        $date = $until->format('Y-m-d');
        foreach (Collateral::query()->where('status', 'active')->whereIn('id', $live)->orderBy('created_at')->get() as $c) {
            $applicationId = (string) (CollateralAllocation::query()->where('collateral_id', $c->id)->where('status', 'active')->orderBy('created_at')->value('application_id') ?? $c->origin_application_id);
            if ($c->requires_valuation && $c->valuation_valid_until !== null && $c->valuation_valid_until->toDateString() <= $date) {
                $out[] = new CollateralAttention('revaluation', $c->id, $applicationId, $c->description, $c->valuation_valid_until->toDateTimeImmutable(), 'val-'.$c->id.'-'.$c->valuation_valid_until->toDateString());
            }
            if (in_array($c->insurance_requirement, ['required', 'waiver_pending'], true)) {
                [, $policy] = $this->assessor->insurance($c);
                if ($policy !== null && $policy->expiry_date->toDateString() <= $date) {
                    $out[] = new CollateralAttention('insurance_expiry', $c->id, $applicationId, $c->description, $policy->expiry_date->toDateTimeImmutable(), 'ins-'.$policy->id);
                }
            }
        }

        return $out;
    }
}

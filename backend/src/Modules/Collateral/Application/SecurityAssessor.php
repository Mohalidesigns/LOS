<?php

declare(strict_types=1);

namespace Fundly\Modules\Collateral\Application;

use Fundly\Modules\Application\Contracts\ApplicationSummary;
use Fundly\Modules\Collateral\Contracts\CollateralCoverage;
use Fundly\Modules\Collateral\Contracts\SecurityPosition;
use Fundly\Modules\Collateral\Domain\Cover;
use Fundly\Modules\Collateral\Infrastructure\Models\Collateral;
use Fundly\Modules\Collateral\Infrastructure\Models\CollateralAllocation;
use Fundly\Modules\Collateral\Infrastructure\Models\InsurancePolicy;
use Fundly\Modules\Collateral\Infrastructure\Models\PerfectionStep;
use Fundly\Shared\Money\Money;

/**
 * Cover, perfection and insurance for an application, computed from the
 * items allocated to it. Shared by the queries and the CollateralRegister
 * contract, so the case screen and the disbursement gate agree.
 */
final class SecurityAssessor
{
    public function __construct(private readonly CollateralFile $file) {}

    /** @return list<CollateralAllocation> */
    public function allocations(string $applicationId): array
    {
        return array_values(CollateralAllocation::query()->where('application_id', $applicationId)->where('status', 'active')->orderBy('created_at')->get()->all());
    }

    /**
     * @param  list<CollateralAllocation>  $allocations
     * @return array<string, Collateral> the active items behind the allocations, by id
     */
    public function items(array $allocations): array
    {
        $ids = array_map(static fn (CollateralAllocation $a): string => $a->collateral_id, $allocations);

        return $ids === [] ? [] : Collateral::query()->whereIn('id', $ids)->where('status', 'active')->get()->keyBy('id')->all();
    }

    /** Lending value allocated across every facility the item secures. */
    public function allocatedTotal(Collateral $c, ?string $exceptApplicationId = null): Money
    {
        $total = Money::zero($c->currency);
        $q = CollateralAllocation::query()->where('collateral_id', $c->id)->where('status', 'active');
        if ($exceptApplicationId !== null) {
            $q->where('application_id', '!=', $exceptApplicationId);
        }
        foreach ($q->get() as $a) {
            $total = $total->plus($a->money());
        }

        return $total;
    }

    public function coverage(ApplicationSummary $app): ?CollateralCoverage
    {
        $allocations = $this->allocations($app->id);
        $items = $this->items($allocations);
        if ($items === []) {
            return null;
        }
        $exposure = $this->file->exposure($app);
        $lending = Money::zero($exposure->currency);
        $market = Money::zero($exposure->currency);
        foreach ($allocations as $a) {
            $c = $items[$a->collateral_id] ?? null;
            if ($c === null || $c->currency !== $exposure->currency->code) {
                continue;
            }
            $effective = Cover::effective($a->money(), $this->allocatedTotal($c), $c->forcedSale());
            $lending = $lending->plus($effective);
            $market = $market->plus(Cover::marketShare($c->market(), $c->forcedSale(), $effective));
        }
        $policy = $this->file->policy($app);
        $cover = new Cover($exposure, $lending, $market);

        return new CollateralCoverage(
            $exposure, $lending, $market, $cover->coverPercent(), $cover->ltvPercent(),
            $policy->toArray()['min_cover_percent'], $policy->toArray()['max_ltv_percent'],
            $cover->meetsCover($policy->minCoverPercent), $cover->meetsLtv($policy->maxLtvPercent), count($items),
        );
    }

    public function security(ApplicationSummary $app): SecurityPosition
    {
        $items = $this->items($this->allocations($app->id));
        $policy = $this->file->policy($app);
        $perfection = [];
        $insurance = [];
        $openSubsequent = 0;
        if ($items === [] && $policy->required) {
            $perfection[] = 'The product requires collateral and none is allocated to this application.';
        }
        $insuranceApplicable = false;
        foreach ($items as $c) {
            foreach (PerfectionStep::query()->where('collateral_id', $c->id)->whereIn('status', PerfectionStep::OPEN)->orderBy('sequence')->get() as $s) {
                if ($s->timing === 'precedent') {
                    $perfection[] = "{$c->description}: {$s->name}".($s->status === 'waiver_pending' ? ' (waiver awaiting a checker)' : '').'.';
                } else {
                    $openSubsequent++;
                }
            }
            [$status] = $this->insurance($c);
            if ($c->insurance_requirement !== 'not_required') {
                $insuranceApplicable = true;
            }
            $why = match ($status) {
                'missing' => 'no insurance policy recorded',
                'expired' => 'the insurance policy has expired',
                'not_started' => 'the insurance policy has not started',
                'interest_not_noted' => "the bank's interest is not noted on the policy",
                default => null,
            };
            if ($why !== null) {
                $insurance[] = "{$c->description}: {$why}".($c->insurance_requirement === 'waiver_pending' ? ' (waiver awaiting a checker)' : '').'.';
            }
        }

        return new SecurityPosition($items !== [] || $policy->required, $insuranceApplicable, $perfection, $insurance, $openSubsequent);
    }

    /**
     * Insurance status of an item and the policy that counts.
     *
     * @return array{0: 'not_required'|'waived'|'in_force'|'expiring'|'missing'|'expired'|'not_started'|'interest_not_noted', 1: ?InsurancePolicy}
     */
    public function insurance(Collateral $c): array
    {
        $today = $this->file->today();
        // the policy that counts: in force (started, not expired) first, then one noting the bank's interest, then the longest running
        $policy = InsurancePolicy::query()->where('collateral_id', $c->id)
            ->orderByRaw('case when start_date <= ? and expiry_date >= ? then 0 else 1 end', [$today->toDateString(), $today->toDateString()])
            ->orderByDesc('bank_interest_noted')->orderByDesc('expiry_date')->orderByDesc('recorded_at')->first();
        $status = match (true) {
            $c->insurance_requirement === 'not_required' => 'not_required',
            $c->insurance_requirement === 'waived' => 'waived',
            $policy === null => 'missing',
            $policy->expiry_date->lessThan($today) => 'expired',
            $policy->start_date->greaterThan($today) => 'not_started',
            ! $policy->bank_interest_noted => 'interest_not_noted',
            $policy->expiry_date->lessThanOrEqualTo($today->copy()->addDays($this->file->noticeDays())) => 'expiring',
            default => 'in_force',
        };

        return [$status, $policy];
    }

    /** @return 'not_required'|'none'|'current'|'due_soon'|'expired' */
    public function valuationStatus(Collateral $c): string
    {
        $today = $this->file->today();

        return match (true) {
            ! $c->requires_valuation => 'not_required',
            $c->value_basis !== 'valuation' => 'none',
            $c->valuation_valid_until === null => 'current',
            $c->valuation_valid_until->lessThan($today) => 'expired',
            $c->valuation_valid_until->lessThanOrEqualTo($today->copy()->addDays($this->file->noticeDays())) => 'due_soon',
            default => 'current',
        };
    }
}

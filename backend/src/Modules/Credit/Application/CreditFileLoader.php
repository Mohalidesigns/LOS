<?php

declare(strict_types=1);

namespace Fundly\Modules\Credit\Application;

use Fundly\Modules\Application\Contracts\ApplicationReader;
use Fundly\Modules\Application\Contracts\ApplicationSummary;
use Fundly\Modules\Credit\Infrastructure\Models\BureauReport;
use Fundly\Modules\Credit\Infrastructure\Models\DecisionSnapshot;
use Fundly\Modules\Platform\Contracts\ActiveConfiguration;
use Fundly\Modules\Platform\Contracts\ConfigVersionView;
use Fundly\Modules\Product\Contracts\ProductCatalogue;
use Fundly\Modules\Product\Contracts\ProductView;
use Fundly\Shared\Clock\Clock;
use Fundly\Shared\Exceptions\DomainRuleViolation;
use Fundly\Shared\Exceptions\NotFound;
use Illuminate\Support\Carbon;

/** Shared lookups for credit commands and queries. */
final class CreditFileLoader
{
    public const RULE_SET_TYPE = 'credit.rule_set';

    public function __construct(
        private readonly ApplicationReader $applications,
        private readonly ProductCatalogue $products,
        private readonly ActiveConfiguration $config,
        private readonly Clock $clock,
    ) {}

    public function application(string $id): ApplicationSummary
    {
        return $this->applications->find($id) ?? throw new NotFound('Application not found.');
    }

    public function product(ApplicationSummary $app): ProductView
    {
        return $this->products->version($app->productVersionId) ?? throw new DomainRuleViolation('The pinned product version is not readable.');
    }

    /** The active version of the rule set the pinned product binds (FR-PRD-005). */
    public function ruleSet(ProductView $product): ConfigVersionView
    {
        $key = $product->binding('rule_set') ?? throw new DomainRuleViolation("Product {$product->key} binds no credit rule set.");

        return $this->config->activeVersion(self::RULE_SET_TYPE, $key) ?? throw new DomainRuleViolation("Credit rule set '{$key}' has no active version.");
    }

    public function validBureau(string $applicationId, string $partyId): ?BureauReport
    {
        return BureauReport::query()->where('application_id', $applicationId)->where('party_id', $partyId)
            ->where('valid_until', '>', Carbon::instance($this->clock->now()))->orderByDesc('pulled_at')->first();
    }

    public function latestDecision(string $applicationId): ?DecisionSnapshot
    {
        return DecisionSnapshot::query()->where('application_id', $applicationId)->orderByDesc('sequence')->first();
    }

    public function primaryPartyId(ApplicationSummary $app): string
    {
        $id = array_search('primary', $app->applicants, true);

        return is_string($id) ? $id : throw new DomainRuleViolation('The application has no primary applicant.');
    }

    public function now(): Carbon
    {
        return Carbon::instance($this->clock->now());
    }
}

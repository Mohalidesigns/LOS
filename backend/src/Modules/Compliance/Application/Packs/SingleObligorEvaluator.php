<?php

declare(strict_types=1);

namespace Fundly\Modules\Compliance\Application\Packs;

use Brick\Math\BigDecimal;
use Fundly\Integration\Ports\CoreBanking\CoreBankingPort;
use Fundly\Integration\Ports\CoreBanking\Dto\CustomerSummary;
use Fundly\Integration\Ports\CoreBanking\Dto\ExposureFacility;
use Fundly\Integration\Runtime\Errors\IntegrationException;
use Fundly\Integration\Runtime\IntegrationGateway;
use Fundly\Modules\Application\Contracts\ApplicationReader;
use Fundly\Modules\Compliance\Contracts\RegulatoryLimitCheck;
use Fundly\Modules\Compliance\Contracts\RegulatoryRules;
use Fundly\Modules\Compliance\Domain\SingleObligor;
use Fundly\Modules\Compliance\Infrastructure\Models\RegulatoryLimitCheckRecord;
use Fundly\Shared\Clock\Clock;
use Fundly\Shared\Exceptions\NotFound;
use Fundly\Shared\Money\Money;

/**
 * Single-obligor check (FR-CMP-023, P1 scope): the primary obligor's
 * existing exposure at the bank (per facility the higher of limit and
 * outstanding, from the core banking system) plus this facility, against
 * the pack's limit percentage of the entity's prudential base in force
 * today. Every check is recorded with the rule and base it used, pass or
 * fail, so it can be re-evaluated later (FR-CMP-003). Connected-party
 * aggregation and insider limits follow in P3.
 */
final class SingleObligorEvaluator
{
    public function __construct(
        private readonly ApplicationReader $applications,
        private readonly RegulatoryRules $rules,
        private readonly PackFile $packs,
        private readonly IntegrationGateway $gateway,
        private readonly Clock $clock,
    ) {}

    public function evaluate(string $applicationId, string $stage, string $actorId): RegulatoryLimitCheck
    {
        $app = $this->applications->find($applicationId) ?? throw new NotFound('Application not found.');
        $now = $this->clock->now();
        $record = new RegulatoryLimitCheckRecord;
        $record->forceFill(['application_id' => $app->id, 'limit_type' => 'single_obligor', 'stage' => $stage, 'facilities' => [], 'checked_by' => $actorId, 'checked_at' => $now]);
        $rule = $this->rules->rule($app->legalEntityId, 'single_obligor_limit', $now);
        $prudential = $this->packs->prudential($app->legalEntityId);
        $entry = $prudential === null ? null : $prudential[0]->at($now);
        $proposed = $app->requestedAmount;

        $result = match (true) {
            $rule === null => ['not_applicable', 'No active jurisdiction pack sets a single-obligor limit for the booking entity.'],
            $entry === null => ['not_applicable', 'No prudential parameters (shareholders\' funds unimpaired) are in force for the booking entity; maintain them under maker-checker (LOS-FR-307).'],
            $proposed === null => ['not_applicable', 'The application has no requested amount.'],
            $entry['base']->currency->code !== $proposed->currency->code => ['not_applicable', 'The prudential base and the facility are in different currencies; FX conversion is not configured.'],
            default => null,
        };
        if ($result === null && $rule !== null && $entry !== null && $proposed !== null) {
            $record->forceFill(['rule_ref' => $rule->reference(), 'base_ref' => ['effective_from' => $entry['effective_from'], 'source' => $entry['source'], 'config_version_id' => $prudential[1]->id ?? null]]);
            $primary = (string) array_search('primary', $app->applicants, true);
            try {
                [$existing, $facilities] = $this->exposure($primary, $app->legalEntityId, $proposed);
            } catch (IntegrationException $e) {
                $result = ['unavailable', "The obligor's exposure could not be read from the core banking system ({$e->canonicalCode}); run the check again."];
                $existing = null;
                $facilities = [];
            }
            if ($existing !== null) {
                $calc = new SingleObligor($entry['base'], BigDecimal::of((string) ($rule->parameters['limit_percent'] ?? '0')), $existing, $proposed);
                $record->forceFill([
                    'currency' => $proposed->currency->code, 'base_amount' => $calc->base->toStorage(), 'limit_percent' => (string) $calc->limitPercent, 'limit_amount' => $calc->limit->toStorage(),
                    'existing_amount' => $existing->toStorage(), 'proposed_amount' => $proposed->toStorage(), 'total_amount' => $calc->total->toStorage(), 'facilities' => $facilities,
                ]);
                $verified = $rule->verificationStatus === 'verified' ? '' : ' (rule not yet verified)';
                $result = $calc->breached()
                    ? ['breach', "Total exposure {$calc->total->currency->code} ".$calc->total->toStorage().' would exceed the single-obligor limit of '.$calc->limit->toStorage()." ({$calc->limitPercent}% of shareholders' funds unimpaired){$verified}."]
                    : ['pass', "Total exposure is {$calc->utilisationPercent()}% of shareholders' funds unimpaired, within the {$calc->limitPercent}% limit; headroom ".$calc->headroom()->toStorage().".{$verified}"];
            }
        }
        $result ??= ['not_applicable', 'The single-obligor limit could not be evaluated.'];
        $record->forceFill(['outcome' => $result[0], 'detail' => $result[1]])->save();

        return new RegulatoryLimitCheck($record->id, $result[0], $result[1]);
    }

    /** @return array{0: Money, 1: list<array<string, mixed>>} existing exposure in the facility currency and the facilities read */
    private function exposure(string $partyId, string $legalEntityId, Money $proposed): array
    {
        $customer = $this->gateway->coreBanking('customer.findByLosReference', static fn (CoreBankingPort $cba): ?CustomerSummary => $cba->customers()->findCustomerByLosReference($partyId), ['los_party_id' => $partyId], null, $legalEntityId);
        $total = Money::zero($proposed->currency);
        if ($customer === null) {
            return [$total, []];
        }
        /** @var list<ExposureFacility> $facilities */
        $facilities = $this->gateway->coreBanking('exposure.get', static fn (CoreBankingPort $cba): array => $cba->exposure()->getExposure($customer->cbaCustomerId), ['cba_customer_id' => $customer->cbaCustomerId], null, $legalEntityId);
        $out = [];
        foreach ($facilities as $f) {
            $amount = $f->limit->isGreaterThan($f->outstanding) ? $f->limit : $f->outstanding;
            $counted = $amount->currency->code === $proposed->currency->code;
            if ($counted) {
                $total = $total->plus($amount);
            }
            $out[] = ['reference' => $f->reference, 'product_code' => $f->productCode, 'amount' => $amount->toArray(), 'classification' => $f->classification, 'counted' => $counted];
        }

        return [$total, $out];
    }
}

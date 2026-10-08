<?php

declare(strict_types=1);

namespace Fundly\Modules\Credit\Infrastructure;

use Fundly\Modules\Credit\Application\CreditFileLoader;
use Fundly\Modules\Credit\Contracts\CreditDecisionView;
use Fundly\Modules\Credit\Contracts\CreditFile;
use Fundly\Modules\Credit\Infrastructure\Models\CreditMemo;
use Fundly\Modules\Credit\Infrastructure\Models\PolicyException;
use Fundly\Shared\Money\Money;

final class DatabaseCreditFile implements CreditFile
{
    private const SEVERITY = ['low' => 1, 'medium' => 2, 'high' => 3];

    public function __construct(private readonly CreditFileLoader $file) {}

    public function latestDecision(string $applicationId): ?CreditDecisionView
    {
        $d = $this->file->latestDecision($applicationId);
        if ($d === null) {
            return null;
        }
        $terms = (array) ($d->outputs['recommended_terms'] ?? []);
        $amount = (array) ($terms['amount'] ?? []);
        $severities = PolicyException::query()->where('decision_id', $d->id)->pluck('severity')->map(static fn ($s): string => (string) $s)->all();
        usort($severities, static fn (string $a, string $b): int => self::SEVERITY[$b] <=> self::SEVERITY[$a]);
        $memo = CreditMemo::query()->where('application_id', $applicationId)->where('decision_id', $d->id)->orderByDesc('version_no')->first();

        return new CreditDecisionView(
            decisionId: $d->id,
            outcome: $d->outcome,
            riskGrade: $d->risk_grade,
            recommendedAmount: Money::of((string) ($amount['amount'] ?? '0'), (string) ($amount['currency'] ?? 'NGN')),
            tenorMonths: (int) ($terms['tenor_months'] ?? 0),
            ratePercent: (string) ($terms['rate_percent'] ?? '0'),
            exceptionCount: count($severities),
            maxExceptionSeverity: $severities[0] ?? null,
            memoRecommendation: $memo?->recommendation,
        );
    }

    public function hasValidBureauReport(string $applicationId): bool
    {
        $app = $this->file->application($applicationId);

        return $this->file->validBureau($applicationId, $this->file->primaryPartyId($app)) !== null;
    }
}

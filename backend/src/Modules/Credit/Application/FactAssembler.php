<?php

declare(strict_types=1);

namespace Fundly\Modules\Credit\Application;

use DateTimeImmutable;
use Fundly\Modules\Application\Contracts\ApplicationSummary;
use Fundly\Modules\Credit\Infrastructure\Models\BureauReport;
use Fundly\Modules\Party\Contracts\PartyDirectory;
use Fundly\Modules\Product\Contracts\ProductView;
use Fundly\Shared\Clock\Clock;

/**
 * Builds the complete fact set *before* evaluation (TRD §7.2): the evaluator
 * does no I/O and the facts are stored verbatim in the snapshot.
 */
final class FactAssembler
{
    public function __construct(private readonly PartyDirectory $parties, private readonly Clock $clock) {}

    /**
     * @param  array<string, mixed>  $data  captured application data (data.*)
     * @return array<string, mixed>
     */
    public function assemble(ApplicationSummary $app, ProductView $product, array $data, ?BureauReport $bureau): array
    {
        $primary = array_search('primary', $app->applicants, true);
        $profile = is_string($primary) ? $this->parties->kycProfile($primary) : null;
        $age = null;
        if ($profile?->dateOfBirth !== null) {
            $age = (int) (new DateTimeImmutable($profile->dateOfBirth))->diff($this->clock->now())->y;
        }
        $p = $bureau === null ? [] : $bureau->profile;
        $facilities = is_array($p['facilities'] ?? null) ? $p['facilities'] : [];
        $interest = is_array($product->content['interest'] ?? null) ? $product->content['interest'] : [];

        return [
            'application' => [
                'reference' => $app->reference,
                'channel' => $app->channel,
                'segment' => $app->segment,
                'currency' => $product->currency,
                'amount' => $app->requestedAmount === null ? null : (string) $app->requestedAmount->amount,
                'tenor_months' => $app->tenorMonths,
            ],
            'product' => [
                'key' => $product->key,
                'version_id' => $product->versionId,
                'category' => $product->category,
                'min_amount' => (string) $product->amountMin->amount,
                'max_amount' => (string) $product->amountMax->amount,
            ],
            'facility' => [
                'amount' => $app->requestedAmount === null ? null : (string) $app->requestedAmount->amount,
                'tenor_months' => $app->tenorMonths,
                'rate_percent' => is_string($interest['rate_percent'] ?? null) ? $interest['rate_percent'] : null,
            ],
            'applicant' => [
                'party_id' => is_string($primary) ? $primary : null,
                'type' => $profile?->type,
                'age' => $age,
                'monthly_income' => self::decimalOrNull($data['monthly_income'] ?? $data['monthly_turnover'] ?? null),
                'years_trading' => is_int($data['years_trading'] ?? null) ? $data['years_trading'] : self::decimalOrNull($data['years_trading'] ?? null),
                'sector' => is_string($data['sector'] ?? null) ? $data['sector'] : null,
                'kyc_verified_ids' => $profile === null ? [] : $profile->verifiedIdentityTypes,
            ],
            'bureau' => $bureau === null ? ['hit' => false] : [
                'hit' => $bureau->hit,
                'report_id' => $bureau->id,
                'score' => $p['score'] ?? null,
                'active_facilities' => count($facilities),
                'total_outstanding' => (string) ($p['total_outstanding']['amount'] ?? '0'),
                'monthly_obligations' => (string) ($p['monthly_obligations']['amount'] ?? '0'),
                'max_dpd_12m' => (int) ($p['max_dpd_12m'] ?? 0),
                'delinquent_facilities' => (int) ($p['delinquent_facilities'] ?? 0),
                'enquiries_6m' => (int) ($p['enquiries_6m'] ?? 0),
                'has_write_off' => (bool) ($p['has_write_off'] ?? false),
            ],
        ];
    }

    private static function decimalOrNull(mixed $v): ?string
    {
        if (is_int($v)) {
            return (string) $v;
        }

        return is_string($v) && preg_match('/^\d+(\.\d+)?$/', $v) === 1 ? $v : null;
    }
}

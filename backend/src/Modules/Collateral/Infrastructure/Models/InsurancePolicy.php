<?php

declare(strict_types=1);

namespace Fundly\Modules\Collateral\Infrastructure\Models;

use Fundly\Shared\Database\TenantModel;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $collateral_id
 * @property string $policy_no
 * @property string $insurer
 * @property string $sum_insured
 * @property string $currency
 * @property Carbon $start_date
 * @property Carbon $expiry_date
 * @property bool $bank_interest_noted
 * @property string|null $document_id
 * @property string|null $document_version_id
 * @property string $recorded_by
 * @property Carbon $recorded_at
 */
final class InsurancePolicy extends TenantModel
{
    public $timestamps = false;

    protected $table = 'collateral_insurance_policies';

    protected function casts(): array
    {
        return ['start_date' => 'date', 'expiry_date' => 'date', 'bank_interest_noted' => 'boolean', 'recorded_at' => 'datetime'];
    }
}

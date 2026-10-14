<?php

declare(strict_types=1);

namespace Fundly\Modules\Collateral\Infrastructure\Models;

use Fundly\Shared\Database\TenantModel;
use Fundly\Shared\Money\Money;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $origin_application_id
 * @property string $type_code
 * @property string $type_source
 * @property string|null $type_version_id
 * @property string $category
 * @property string $description
 * @property string|null $owner_party_id
 * @property string $owner_name
 * @property string|null $state_code
 * @property string $currency
 * @property string $market_value
 * @property string $haircut_percent
 * @property string $forced_sale_value
 * @property string $value_basis
 * @property bool $requires_valuation
 * @property Carbon|null $valuation_valid_until
 * @property string $insurance_requirement
 * @property string|null $insurance_waiver_reason
 * @property string|null $insurance_waiver_change_request_id
 * @property string $status
 * @property string $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class Collateral extends TenantModel
{
    protected $table = 'collaterals';

    public function market(): Money
    {
        return Money::of($this->market_value, $this->currency);
    }

    public function forcedSale(): Money
    {
        return Money::of($this->forced_sale_value, $this->currency);
    }

    protected function casts(): array
    {
        return ['requires_valuation' => 'boolean', 'valuation_valid_until' => 'date'];
    }
}

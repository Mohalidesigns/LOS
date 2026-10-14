<?php

declare(strict_types=1);

namespace Fundly\Modules\Collateral\Infrastructure\Models;

use Fundly\Shared\Database\TenantModel;
use Fundly\Shared\Money\Money;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $collateral_id
 * @property string $application_id
 * @property string $amount
 * @property string $currency
 * @property string $status
 * @property string $created_by
 * @property string $updated_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class CollateralAllocation extends TenantModel
{
    protected $table = 'collateral_allocations';

    public function money(): Money
    {
        return Money::of($this->amount, $this->currency);
    }
}

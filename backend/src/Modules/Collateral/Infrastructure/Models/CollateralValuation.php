<?php

declare(strict_types=1);

namespace Fundly\Modules\Collateral\Infrastructure\Models;

use Fundly\Shared\Database\TenantModel;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $collateral_id
 * @property int $sequence
 * @property string $valuer
 * @property Carbon $valuation_date
 * @property string $method
 * @property string $market_value
 * @property string $forced_sale_value
 * @property string $currency
 * @property Carbon|null $valid_until
 * @property string|null $report_document_id
 * @property string|null $report_version_id
 * @property string|null $note
 * @property string $recorded_by
 * @property Carbon $recorded_at
 */
final class CollateralValuation extends TenantModel
{
    public $timestamps = false;

    protected $table = 'collateral_valuations';

    protected function casts(): array
    {
        return ['sequence' => 'integer', 'valuation_date' => 'date', 'valid_until' => 'date', 'recorded_at' => 'datetime'];
    }
}

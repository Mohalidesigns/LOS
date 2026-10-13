<?php

declare(strict_types=1);

namespace Fundly\Modules\Disbursement\Infrastructure\Models;

use Fundly\Shared\Database\TenantModel;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property Carbon $from_date
 * @property Carbon $to_date
 * @property int $los_postings
 * @property int $cba_postings
 * @property int $matched
 * @property int $breaks
 * @property string $ran_by
 * @property Carbon $ran_at
 */
final class ReconciliationRun extends TenantModel
{
    public $timestamps = false;

    protected $table = 'reconciliation_runs';

    protected function casts(): array
    {
        return ['from_date' => 'date', 'to_date' => 'date', 'los_postings' => 'integer', 'cba_postings' => 'integer', 'matched' => 'integer', 'breaks' => 'integer', 'ran_at' => 'datetime'];
    }
}

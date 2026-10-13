<?php

declare(strict_types=1);

namespace Fundly\Modules\Disbursement\Infrastructure\Models;

use Fundly\Shared\Database\TenantModel;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $run_id
 * @property string $kind
 * @property string $reference
 * @property string|null $posting_ref
 * @property string|null $account_no
 * @property string|null $los_amount
 * @property string|null $cba_amount
 * @property string|null $currency
 * @property string|null $disbursement_id
 * @property string $status
 * @property string|null $resolved_by
 * @property Carbon|null $resolved_at
 * @property string|null $resolution_note
 */
final class ReconciliationBreak extends TenantModel
{
    public $timestamps = false;

    protected $table = 'reconciliation_breaks';

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }
}

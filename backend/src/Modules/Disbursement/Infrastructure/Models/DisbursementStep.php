<?php

declare(strict_types=1);

namespace Fundly\Modules\Disbursement\Infrastructure\Models;

use Fundly\Shared\Database\TenantModel;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $disbursement_id
 * @property int $run
 * @property string $step
 * @property string $outcome
 * @property array<string, mixed> $detail
 * @property string|null $error_code
 * @property Carbon $occurred_at
 */
final class DisbursementStep extends TenantModel
{
    public $timestamps = false;

    protected $table = 'disbursement_steps';

    protected function casts(): array
    {
        return ['run' => 'integer', 'detail' => 'array', 'occurred_at' => 'datetime'];
    }
}

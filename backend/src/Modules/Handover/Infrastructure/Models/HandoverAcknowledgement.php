<?php

declare(strict_types=1);

namespace Fundly\Modules\Handover\Infrastructure\Models;

use Fundly\Shared\Database\TenantModel;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $package_id
 * @property string $acknowledged_by
 * @property string $note
 * @property Carbon $acknowledged_at
 */
final class HandoverAcknowledgement extends TenantModel
{
    public $timestamps = false;

    protected $table = 'handover_acknowledgements';

    protected function casts(): array
    {
        return ['acknowledged_at' => 'datetime'];
    }
}

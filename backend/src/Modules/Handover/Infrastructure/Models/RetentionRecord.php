<?php

declare(strict_types=1);

namespace Fundly\Modules\Handover\Infrastructure\Models;

use Fundly\Shared\Database\TenantModel;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $application_id
 * @property string $category
 * @property string $trigger
 * @property Carbon $starts_on
 * @property Carbon $retain_until
 * @property bool $legal_hold
 * @property string $basis
 * @property Carbon $created_at
 */
final class RetentionRecord extends TenantModel
{
    public $timestamps = false;

    protected $table = 'retention_records';

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'retain_until' => 'date', 'legal_hold' => 'boolean', 'created_at' => 'datetime'];
    }
}

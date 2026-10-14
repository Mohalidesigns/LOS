<?php

declare(strict_types=1);

namespace Fundly\Modules\Compliance\Infrastructure\Models;

use Fundly\Shared\Database\TenantModel;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $application_id
 * @property string $limit_type
 * @property string $stage
 * @property string $outcome
 * @property string $detail
 * @property array<string, mixed>|null $rule_ref
 * @property array<string, mixed>|null $base_ref
 * @property string|null $currency
 * @property string|null $base_amount
 * @property string|null $limit_percent
 * @property string|null $limit_amount
 * @property string|null $existing_amount
 * @property string|null $proposed_amount
 * @property string|null $total_amount
 * @property array<int, array<string, mixed>> $facilities
 * @property string $checked_by
 * @property Carbon $checked_at
 */
final class RegulatoryLimitCheckRecord extends TenantModel
{
    public $timestamps = false;

    protected $table = 'regulatory_limit_checks';

    protected function casts(): array
    {
        return ['rule_ref' => 'array', 'base_ref' => 'array', 'facilities' => 'array', 'checked_at' => 'datetime'];
    }
}

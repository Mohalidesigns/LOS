<?php

declare(strict_types=1);

namespace Fundly\Modules\Approval\Infrastructure\Models;

use Fundly\Shared\Database\TenantModel;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $application_id
 * @property int $sequence
 * @property string $status
 * @property string $matrix_key
 * @property string $matrix_version_id
 * @property int $matrix_version_no
 * @property int|null $matrix_row
 * @property array<string, mixed> $facts
 * @property list<array<string, mixed>> $routing_trace
 * @property list<array{code: string, name: string, roles: list<string>, mode: string, added_by: string, note: ?string}> $levels
 * @property int $current_level
 * @property string|null $decision_id
 * @property string $exposure_amount
 * @property string $exposure_currency
 * @property string $recommended_by
 * @property Carbon $opened_at
 * @property Carbon|null $decided_at
 * @property int $validity_days
 * @property Carbon|null $valid_until
 * @property array<string, mixed>|null $counter_terms
 */
final class ApprovalRequest extends TenantModel
{
    public $timestamps = false;

    protected $table = 'approval_requests';

    protected function casts(): array
    {
        return [
            'sequence' => 'integer', 'matrix_version_no' => 'integer', 'matrix_row' => 'integer', 'current_level' => 'integer', 'validity_days' => 'integer',
            'facts' => 'array', 'routing_trace' => 'array', 'levels' => 'array', 'counter_terms' => 'array',
            'opened_at' => 'datetime', 'decided_at' => 'datetime', 'valid_until' => 'datetime',
        ];
    }
}

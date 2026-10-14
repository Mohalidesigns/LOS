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
 * @property string $code
 * @property string $name
 * @property string $timing
 * @property string|null $responsible_user_id
 * @property Carbon|null $due_date
 * @property string $status
 * @property string|null $evidence_document_id
 * @property string|null $evidence_version_id
 * @property string|null $evidence_reference
 * @property string|null $note
 * @property string|null $completed_by
 * @property Carbon|null $completed_at
 * @property string|null $waiver_reason
 * @property string|null $waiver_change_request_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class PerfectionStep extends TenantModel
{
    public const OPEN = ['pending', 'in_progress', 'waiver_pending'];

    protected $table = 'collateral_perfection_steps';

    protected function casts(): array
    {
        return ['sequence' => 'integer', 'due_date' => 'date', 'completed_at' => 'datetime'];
    }
}

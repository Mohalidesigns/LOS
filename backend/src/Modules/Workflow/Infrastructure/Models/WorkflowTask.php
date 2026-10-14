<?php

declare(strict_types=1);

namespace Fundly\Modules\Workflow\Infrastructure\Models;

use Fundly\Shared\Database\TenantModel;
use Fundly\Shared\Security\ResourceAttributes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $application_id
 * @property string $application_reference
 * @property string|null $applicant_name
 * @property string $legal_entity_id
 * @property string $org_unit_id
 * @property string $product_id
 * @property string $segment
 * @property string $stage_code
 * @property string $stage_name
 * @property string $canonical_status
 * @property string $permission
 * @property string $strategy
 * @property string $status
 * @property string|null $assignee_id
 * @property Carbon|null $assigned_at
 * @property int|null $warn_minutes
 * @property int|null $breach_minutes
 * @property Carbon|null $warn_at
 * @property Carbon|null $breach_at
 * @property string $sla_state
 * @property string|null $escalate_to
 * @property Carbon|null $warned_at
 * @property Carbon|null $breached_at
 * @property Carbon|null $paused_at
 * @property int $paused_minutes
 * @property Carbon $opened_at
 * @property Carbon|null $closed_at
 * @property string|null $closed_by
 * @property string|null $close_reason
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class WorkflowTask extends TenantModel
{
    protected $table = 'workflow_tasks';

    public function resource(string $entityType = 'workflow_task'): ResourceAttributes
    {
        return new ResourceAttributes(legalEntityId: $this->legal_entity_id, orgUnitId: $this->org_unit_id, productId: $this->product_id, segment: $this->segment, entityType: $entityType, entityId: $entityType === 'application' ? $this->application_id : $this->id);
    }

    protected function casts(): array
    {
        return [
            'warn_minutes' => 'integer', 'breach_minutes' => 'integer', 'paused_minutes' => 'integer',
            'assigned_at' => 'datetime', 'warn_at' => 'datetime', 'breach_at' => 'datetime', 'warned_at' => 'datetime', 'breached_at' => 'datetime',
            'paused_at' => 'datetime', 'opened_at' => 'datetime', 'closed_at' => 'datetime',
        ];
    }
}

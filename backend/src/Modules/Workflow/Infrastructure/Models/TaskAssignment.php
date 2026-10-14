<?php

declare(strict_types=1);

namespace Fundly\Modules\Workflow\Infrastructure\Models;

use Fundly\Shared\Database\TenantModel;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $task_id
 * @property string $kind
 * @property string|null $from_user_id
 * @property string|null $to_user_id
 * @property string $by_actor
 * @property string|null $on_behalf_of
 * @property string|null $reason
 * @property Carbon $at
 */
final class TaskAssignment extends TenantModel
{
    public $timestamps = false;

    protected $table = 'workflow_task_assignments';

    protected function casts(): array
    {
        return ['at' => 'datetime'];
    }
}

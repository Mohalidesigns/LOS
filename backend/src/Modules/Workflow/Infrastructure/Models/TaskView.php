<?php

declare(strict_types=1);

namespace Fundly\Modules\Workflow\Infrastructure\Models;

use Fundly\Shared\Database\TenantModel;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $user_id
 * @property string $name
 * @property array<string, string> $filters
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class TaskView extends TenantModel
{
    protected $table = 'task_views';

    protected function casts(): array
    {
        return ['filters' => 'array'];
    }
}

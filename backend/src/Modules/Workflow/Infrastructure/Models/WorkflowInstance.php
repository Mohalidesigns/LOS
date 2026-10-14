<?php

declare(strict_types=1);

namespace Fundly\Modules\Workflow\Infrastructure\Models;

use Fundly\Shared\Database\TenantModel;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $application_id
 * @property string $workflow_key
 * @property string $source
 * @property string|null $version_id
 * @property int|null $version_no
 * @property Carbon $pinned_at
 * @property string|null $migrated_from_version_id
 * @property string|null $migrated_by
 * @property Carbon|null $migrated_at
 */
final class WorkflowInstance extends TenantModel
{
    public $timestamps = false;

    protected $table = 'workflow_instances';

    protected function casts(): array
    {
        return ['version_no' => 'integer', 'pinned_at' => 'datetime', 'migrated_at' => 'datetime'];
    }
}

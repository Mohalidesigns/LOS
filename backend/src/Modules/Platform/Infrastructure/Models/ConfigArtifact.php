<?php

declare(strict_types=1);

namespace Fundly\Modules\Platform\Infrastructure\Models;

use Fundly\Shared\Database\TenantModel;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $type
 * @property string $key
 * @property string $name
 * @property string|null $description
 * @property string|null $active_version_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class ConfigArtifact extends TenantModel
{
    protected $table = 'config_artifacts';
}

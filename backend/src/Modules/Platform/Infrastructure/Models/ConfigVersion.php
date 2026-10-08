<?php

declare(strict_types=1);

namespace Fundly\Modules\Platform\Infrastructure\Models;

use Fundly\Shared\Database\TenantModel;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $artifact_id
 * @property int $version_no
 * @property string $status
 * @property array<string, mixed> $content
 * @property string $content_hash
 * @property string|null $notes
 * @property string $created_by
 * @property string|null $submitted_by
 * @property Carbon|null $submitted_at
 * @property string|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property string|null $review_reason
 * @property string|null $activated_by
 * @property Carbon|null $activated_at
 * @property string|null $activation_change_request_id
 * @property Carbon|null $superseded_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class ConfigVersion extends TenantModel
{
    protected $table = 'config_versions';

    protected function casts(): array
    {
        return [
            'content' => 'array',
            'version_no' => 'integer',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'activated_at' => 'datetime',
            'superseded_at' => 'datetime',
        ];
    }
}

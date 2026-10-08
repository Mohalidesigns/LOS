<?php

declare(strict_types=1);

namespace Fundly\Modules\Platform\Infrastructure\Models;

use Fundly\Shared\Database\TenantModel;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $code
 * @property string $name
 * @property string $jurisdiction
 * @property string $licence_category
 * @property string $base_currency
 * @property string $timezone
 * @property list<string> $org_level_labels
 * @property string $status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class LegalEntity extends TenantModel
{
    protected $table = 'legal_entities';

    protected function casts(): array
    {
        return ['org_level_labels' => 'array'];
    }
}

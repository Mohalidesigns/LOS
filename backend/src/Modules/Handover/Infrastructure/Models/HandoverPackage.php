<?php

declare(strict_types=1);

namespace Fundly\Modules\Handover\Infrastructure\Models;

use Fundly\Shared\Database\TenantModel;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $application_id
 * @property string $disbursement_id
 * @property string $package_json
 * @property string $sha256
 * @property string $servicing_org_unit_id
 * @property string|null $webhook_event_id
 * @property string $created_by
 * @property Carbon $created_at
 */
final class HandoverPackage extends TenantModel
{
    public $timestamps = false;

    protected $table = 'handover_packages';

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}

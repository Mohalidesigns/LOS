<?php

declare(strict_types=1);

namespace Fundly\Modules\Application\Infrastructure\Models;

use Fundly\Shared\Database\TenantModel;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $application_id
 * @property string $party_id
 * @property string $role
 * @property string $party_type
 * @property string $display_name
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class ApplicantRecord extends TenantModel
{
    protected $table = 'application_applicants';
}

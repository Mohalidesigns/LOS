<?php

declare(strict_types=1);

namespace Fundly\Modules\Approval\Infrastructure\Models;

use Fundly\Shared\Database\TenantModel;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $application_id
 * @property string $request_id
 * @property string $vote_id
 * @property string $kind
 * @property string $description
 * @property string $status
 * @property Carbon $created_at
 */
final class ApprovalCondition extends TenantModel
{
    public $timestamps = false;

    protected $table = 'approval_conditions';

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}

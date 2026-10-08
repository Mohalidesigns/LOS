<?php

declare(strict_types=1);

namespace Fundly\Modules\Credit\Infrastructure\Models;

use Fundly\Shared\Database\TenantModel;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $application_id
 * @property string $decision_id
 * @property string $reason_code
 * @property string $justification
 * @property string|null $evidence_ref
 * @property string $severity
 * @property string $raised_by
 * @property Carbon $raised_at
 */
final class PolicyException extends TenantModel
{
    public $timestamps = false;

    protected $table = 'policy_exceptions';

    protected function casts(): array
    {
        return ['raised_at' => 'datetime'];
    }
}

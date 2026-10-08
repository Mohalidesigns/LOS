<?php

declare(strict_types=1);

namespace Fundly\Modules\Compliance\Infrastructure\Models;

use Fundly\Shared\Database\TenantModel;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $screening_run_id
 * @property string $party_id
 * @property string|null $application_id
 * @property string|null $org_unit_id
 * @property string $category
 * @property string $list_name
 * @property string $list_version
 * @property string $entry_id
 * @property string $matched_name
 * @property string $score
 * @property string $status
 * @property string|null $proposed_decision
 * @property string|null $proposed_reason
 * @property string|null $evidence_ref
 * @property string|null $proposed_by
 * @property Carbon|null $proposed_at
 * @property string|null $confirmed_by
 * @property Carbon|null $confirmed_at
 * @property string|null $confirmation_note
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class ScreeningAlert extends TenantModel
{
    protected $table = 'screening_alerts';

    protected function casts(): array
    {
        return ['proposed_at' => 'datetime', 'confirmed_at' => 'datetime'];
    }
}

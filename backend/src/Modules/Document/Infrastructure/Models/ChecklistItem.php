<?php

declare(strict_types=1);

namespace Fundly\Modules\Document\Infrastructure\Models;

use Fundly\Shared\Database\TenantModel;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $application_id
 * @property string $code
 * @property string $name
 * @property int $position
 * @property bool $mandatory
 * @property string $status
 * @property string $waiver_authority
 * @property int|null $validity_days
 * @property string|null $document_id
 * @property Carbon|null $valid_until
 * @property string|null $rejection_reason
 * @property string|null $waiver_reason
 * @property string|null $waiver_change_request_id
 * @property string|null $verified_by
 * @property Carbon|null $verified_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class ChecklistItem extends TenantModel
{
    protected $table = 'checklist_items';

    protected function casts(): array
    {
        return ['mandatory' => 'boolean', 'position' => 'integer', 'validity_days' => 'integer', 'valid_until' => 'date', 'verified_at' => 'datetime'];
    }
}

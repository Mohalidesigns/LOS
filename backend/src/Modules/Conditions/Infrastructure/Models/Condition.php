<?php

declare(strict_types=1);

namespace Fundly\Modules\Conditions\Infrastructure\Models;

use Fundly\Shared\Database\TenantModel;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $application_id
 * @property string $kind
 * @property string $source
 * @property string|null $source_ref
 * @property string $description
 * @property bool $mandatory
 * @property string|null $owner_id
 * @property Carbon|null $due_date
 * @property string $status
 * @property string|null $evidence_document_id
 * @property string|null $evidence_version_id
 * @property string|null $evidence_reference
 * @property string|null $signoff_note
 * @property string|null $signed_off_by
 * @property Carbon|null $signed_off_at
 * @property string|null $waiver_reason
 * @property string|null $waiver_change_request_id
 * @property string $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class Condition extends TenantModel
{
    protected $table = 'conditions';

    protected function casts(): array
    {
        return ['mandatory' => 'boolean', 'due_date' => 'date', 'signed_off_at' => 'datetime'];
    }
}

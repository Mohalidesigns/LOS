<?php

declare(strict_types=1);

namespace Fundly\Modules\Workflow\Infrastructure\Models;

use Fundly\Shared\Database\TenantModel;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $application_id
 * @property string $author_id
 * @property string $body
 * @property string $visibility
 * @property list<string> $mentions
 * @property Carbon $created_at
 */
final class CaseNote extends TenantModel
{
    public $timestamps = false;

    protected $table = 'case_notes';

    protected function casts(): array
    {
        return ['mentions' => 'array', 'created_at' => 'datetime'];
    }
}

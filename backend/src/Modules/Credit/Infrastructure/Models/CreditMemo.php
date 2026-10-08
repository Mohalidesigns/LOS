<?php

declare(strict_types=1);

namespace Fundly\Modules\Credit\Infrastructure\Models;

use Fundly\Shared\Database\TenantModel;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $application_id
 * @property int $version_no
 * @property string $decision_id
 * @property list<array{key: string, title: string, content: array<string, mixed>}> $sections
 * @property string $narrative
 * @property string $recommendation
 * @property string|null $recommended_amount
 * @property int|null $recommended_tenor_months
 * @property list<string> $conditions
 * @property string $authored_by
 * @property Carbon $authored_at
 */
final class CreditMemo extends TenantModel
{
    public $timestamps = false;

    protected $table = 'credit_memos';

    protected function casts(): array
    {
        return ['version_no' => 'integer', 'recommended_tenor_months' => 'integer', 'sections' => 'array', 'conditions' => 'array', 'authored_at' => 'datetime'];
    }
}

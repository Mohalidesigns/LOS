<?php

declare(strict_types=1);

namespace Fundly\Modules\Credit\Infrastructure\Models;

use Fundly\Shared\Database\TenantModel;
use Illuminate\Support\Carbon;

/**
 * Immutable record of one decision run (FR-CRD-014): inputs, versions,
 * outputs and trace.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $application_id
 * @property int $sequence
 * @property string $outcome
 * @property string $risk_grade
 * @property string $rule_set_version_id
 * @property string $rule_set_key
 * @property int $rule_set_version_no
 * @property string $evaluator_version
 * @property string|null $bureau_report_id
 * @property array<string, mixed> $facts
 * @property array<string, mixed> $outputs
 * @property list<array<string, mixed>> $trace
 * @property string $decided_by
 * @property Carbon $decided_at
 */
final class DecisionSnapshot extends TenantModel
{
    public $timestamps = false;

    protected $table = 'decision_snapshots';

    protected function casts(): array
    {
        return ['sequence' => 'integer', 'rule_set_version_no' => 'integer', 'facts' => 'array', 'outputs' => 'array', 'trace' => 'array', 'decided_at' => 'datetime'];
    }
}

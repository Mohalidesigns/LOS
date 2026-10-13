<?php

declare(strict_types=1);

namespace Fundly\Modules\Conditions\Infrastructure\Models;

use Fundly\Shared\Database\TenantModel;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $application_id
 * @property int $sequence
 * @property bool $passed
 * @property list<array{code: string, label: string, status: string, detail: string}> $items
 * @property string $bank_code
 * @property string $account_no_enc
 * @property string $account_no_masked
 * @property string|null $account_name
 * @property int|null $name_match_score
 * @property array<string, int> $screening
 * @property string $checked_by
 * @property Carbon $checked_at
 * @property Carbon $valid_until
 */
final class PreDisbursementCheck extends TenantModel
{
    public $timestamps = false;

    protected $table = 'pre_disbursement_checks';

    protected function casts(): array
    {
        return ['sequence' => 'integer', 'passed' => 'boolean', 'items' => 'array', 'name_match_score' => 'integer', 'screening' => 'array', 'checked_at' => 'datetime', 'valid_until' => 'datetime'];
    }
}

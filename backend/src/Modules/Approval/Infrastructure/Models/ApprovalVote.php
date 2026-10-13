<?php

declare(strict_types=1);

namespace Fundly\Modules\Approval\Infrastructure\Models;

use Fundly\Shared\Database\TenantModel;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $request_id
 * @property string $application_id
 * @property int $level_index
 * @property string $level_code
 * @property string $voter_id
 * @property string $decision
 * @property string $rationale
 * @property list<string> $reason_codes
 * @property list<array{kind: string, description: string}> $conditions
 * @property array<string, mixed>|null $counter_terms
 * @property array<string, mixed> $authority_basis
 * @property string|null $step_up_ref
 * @property Carbon $voted_at
 */
final class ApprovalVote extends TenantModel
{
    public $timestamps = false;

    protected $table = 'approval_votes';

    protected function casts(): array
    {
        return ['level_index' => 'integer', 'reason_codes' => 'array', 'conditions' => 'array', 'counter_terms' => 'array', 'authority_basis' => 'array', 'voted_at' => 'datetime'];
    }
}

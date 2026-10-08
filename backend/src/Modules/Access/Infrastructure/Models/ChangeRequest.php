<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Infrastructure\Models;

use Fundly\Shared\Database\TenantModel;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $action_type
 * @property string|null $entity_type
 * @property string|null $entity_id
 * @property array<string, mixed> $payload
 * @property string $payload_hash
 * @property string $state_fingerprint
 * @property string $required_checker_permission
 * @property string $status
 * @property string $maker_id
 * @property string|null $maker_reason
 * @property string|null $checker_id
 * @property string|null $decision_reason
 * @property string|null $step_up_ref
 * @property array<string, mixed>|null $execution_result
 * @property string|null $correlation_id
 * @property Carbon|null $decided_at
 * @property Carbon|null $executed_at
 * @property Carbon|null $expires_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class ChangeRequest extends TenantModel
{
    protected $table = 'change_requests';

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'execution_result' => 'array',
            'decided_at' => 'datetime',
            'executed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}

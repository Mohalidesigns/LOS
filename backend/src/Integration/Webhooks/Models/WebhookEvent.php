<?php

declare(strict_types=1);

namespace Fundly\Integration\Webhooks\Models;

use Fundly\Shared\Database\TenantModel;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $type
 * @property string $subject_type
 * @property string $subject_id
 * @property array<string, mixed> $payload
 * @property Carbon $occurred_at
 */
final class WebhookEvent extends TenantModel
{
    public $timestamps = false;

    protected $table = 'webhook_events';

    protected function casts(): array
    {
        return ['payload' => 'array', 'occurred_at' => 'datetime'];
    }
}

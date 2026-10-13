<?php

declare(strict_types=1);

namespace Fundly\Integration\Webhooks\Models;

use Fundly\Shared\Database\TenantModel;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $event_id
 * @property string $endpoint_id
 * @property string $status
 * @property int $attempts
 * @property int|null $last_http_status
 * @property string|null $last_error
 * @property Carbon|null $last_attempt_at
 * @property Carbon|null $delivered_at
 * @property Carbon $created_at
 */
final class WebhookDelivery extends TenantModel
{
    public $timestamps = false;

    protected $table = 'webhook_deliveries';

    protected function casts(): array
    {
        return ['attempts' => 'integer', 'last_http_status' => 'integer', 'last_attempt_at' => 'datetime', 'delivered_at' => 'datetime', 'created_at' => 'datetime'];
    }
}

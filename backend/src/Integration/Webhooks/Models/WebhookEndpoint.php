<?php

declare(strict_types=1);

namespace Fundly\Integration\Webhooks\Models;

use Fundly\Shared\Database\TenantModel;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $name
 * @property string $url
 * @property string $secret_enc
 * @property list<string> $events
 * @property string $status
 * @property string $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class WebhookEndpoint extends TenantModel
{
    protected $table = 'webhook_endpoints';

    protected function casts(): array
    {
        return ['events' => 'array'];
    }
}

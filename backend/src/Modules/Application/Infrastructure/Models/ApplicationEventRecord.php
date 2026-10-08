<?php

declare(strict_types=1);

namespace Fundly\Modules\Application\Infrastructure\Models;

use Fundly\Shared\Database\TenantModel;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $application_id
 * @property int $version
 * @property string $type
 * @property array<string, mixed> $payload
 * @property string $actor_type
 * @property string $actor_id
 * @property string|null $correlation_id
 * @property Carbon $occurred_at
 */
final class ApplicationEventRecord extends TenantModel
{
    public $timestamps = false;

    protected $table = 'application_events';

    protected function casts(): array
    {
        return ['payload' => 'array', 'version' => 'integer', 'occurred_at' => 'datetime'];
    }
}

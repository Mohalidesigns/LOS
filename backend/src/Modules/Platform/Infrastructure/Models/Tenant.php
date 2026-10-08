<?php

declare(strict_types=1);

namespace Fundly\Modules\Platform\Infrastructure\Models;

use Fundly\Shared\Database\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $slug
 * @property string $name
 * @property string $status
 * @property string|null $hostname
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class Tenant extends Model
{
    protected $table = 'tenants';
}

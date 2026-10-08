<?php

declare(strict_types=1);

namespace Fundly\Shared\Database;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model as EloquentModel;

/**
 * Base Eloquent model: UUIDv7 keys (Laravel's HasUuids generates v7),
 * timestamptz with microseconds, and no mass assignment from requests
 * (commands are explicit DTOs, TRD §8.4).
 */
abstract class Model extends EloquentModel
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $dateFormat = 'Y-m-d H:i:s.uP';

    /** Writes happen only in command handlers with explicit attribute lists. */
    protected $guarded = [];
}

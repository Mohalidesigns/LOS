<?php

declare(strict_types=1);

namespace Fundly\Shared\Database;

use Illuminate\Database\Migrations\Migration as BaseMigration;
use Illuminate\Support\Facades\DB;

/**
 * Base class for Fundly migrations. Migrations always run as the schema owner.
 */
abstract class Migration extends BaseMigration
{
    protected function pg(): PostgresSchema
    {
        $role = config('fundly.database.app_role');

        return new PostgresSchema(DB::connection($this->getConnection()), is_string($role) ? $role : 'fundly_app');
    }
}

<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * RefreshDatabase on PostgreSQL with the two-role model: the schema is
 * rebuilt once per run *as the owner role*, and each test runs inside a
 * transaction on the *runtime* (non-owner, RLS-subject) connection.
 */
trait RefreshesPostgres
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    protected function migrateFreshUsing(): array
    {
        return ['--database' => 'pgsql_owner', '--drop-views' => true, '--force' => true];
    }

    /** @return list<string> */
    protected function connectionsToTransact(): array
    {
        return ['pgsql'];
    }

    protected function afterRefreshingDatabase(): void
    {
        $this->installLicence();
    }
}

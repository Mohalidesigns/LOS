<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;

/**
 * For tests that must see committed data from a second connection (the
 * owner role), such as RLS bypass attempts and audit tamper detection. Data
 * is committed for real; afterwards every table is truncated by the owner
 * with the immutability triggers temporarily disabled.
 */
trait CommitsToDatabase
{
    protected function setUpCommitsToDatabase(): void
    {
        if (! RefreshDatabaseState::$migrated) {
            $this->artisan('migrate:fresh', ['--database' => 'pgsql_owner', '--force' => true]);
            RefreshDatabaseState::$migrated = true;
        }
        self::truncateAll();
        $this->installLicence();
        $this->beforeApplicationDestroyed(static fn () => self::truncateAll());
    }

    public static function truncateAll(): void
    {
        $owner = DB::connection('pgsql_owner');
        $tables = array_map(static fn ($r) => $r->tablename, $owner->select("select tablename from pg_tables where schemaname = 'public' and tablename not in ('migrations', 'permissions')"));
        $immutable = ['audit_events', 'audit_checkpoints', 'licence_events'];
        foreach ($immutable as $t) {
            $owner->statement("alter table {$t} disable trigger user");
        }
        $owner->statement('truncate table '.implode(', ', $tables).' restart identity cascade');
        foreach ($immutable as $t) {
            $owner->statement("alter table {$t} enable trigger user");
        }
    }
}

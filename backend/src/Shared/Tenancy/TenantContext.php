<?php

declare(strict_types=1);

namespace Fundly\Shared\Tenancy;

use Fundly\Shared\Id\UuidV7;
use Illuminate\Database\ConnectionInterface;

/**
 * Holds the current tenant and mirrors it into the PostgreSQL session setting
 * `app.tenant_id` on the runtime connection, which every RLS policy reads
 * (TRD §2.3, D-033).
 *
 * The tenant always comes from the authenticated principal (or, for a queued
 * job, from the job payload), never from a request body. Callers must restore
 * the previous value when they finish; {@see run()} does this for you.
 */
final class TenantContext
{
    private ?string $tenantId = null;

    public function __construct(private readonly ConnectionInterface $connection)
    {
    }

    public function set(string $tenantId): void
    {
        $tenantId = UuidV7::assertValid($tenantId);
        $this->connection->select("select set_config('app.tenant_id', ?, false)", [$tenantId]);
        $this->tenantId = $tenantId;
    }

    public function clear(): void
    {
        $this->connection->select("select set_config('app.tenant_id', '', false)");
        $this->tenantId = null;
    }

    public function id(): ?string
    {
        return $this->tenantId;
    }

    public function requireId(): string
    {
        return $this->tenantId ?? throw new NoTenantContext('No tenant context is set.');
    }

    public function has(): bool
    {
        return $this->tenantId !== null;
    }

    /**
     * Run a callback inside a tenant context and restore the previous one.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function run(string $tenantId, callable $callback): mixed
    {
        $previous = $this->tenantId;
        $this->set($tenantId);
        try {
            $result = $callback();
        } catch (\Throwable $e) {
            // If the failure aborted the surrounding transaction, the restore
            // statement fails too; never let that mask the original error.
            try {
                $this->restore($previous);
            } catch (\Throwable) {
                $this->tenantId = $previous;
            }
            throw $e;
        }
        $this->restore($previous);

        return $result;
    }

    public function restore(?string $previous): void
    {
        if ($previous === null) {
            $this->clear();
        } else {
            $this->set($previous);
        }
    }

    /** What PostgreSQL currently believes the tenant is (used by tests and readiness checks). */
    public function databaseValue(): ?string
    {
        $row = $this->connection->selectOne("select nullif(current_setting('app.tenant_id', true), '') as t");

        return is_object($row) && isset($row->t) && is_string($row->t) ? $row->t : null;
    }
}

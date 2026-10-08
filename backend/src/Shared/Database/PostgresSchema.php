<?php

declare(strict_types=1);

namespace Fundly\Shared\Database;

use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;

/**
 * Migration helpers for the PostgreSQL controls the TRD relies on:
 * row-level security, explicit grants to the runtime role, and immutability
 * triggers. Migrations run as the schema owner; the runtime role only gets
 * what is granted here.
 */
final class PostgresSchema
{
    public const TENANT_PREDICATE = "tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid";

    public function __construct(private readonly ConnectionInterface $db, private readonly string $appRole)
    {
        self::assertIdentifier($appRole);
    }

    /**
     * ENABLE + FORCE row-level security with the tenant isolation policy.
     * FORCE makes the policy apply to the table owner too.
     */
    public function tenantRls(string $table): void
    {
        self::assertIdentifier($table);
        $this->db->statement("alter table {$table} enable row level security");
        $this->db->statement("alter table {$table} force row level security");
        $this->db->statement("drop policy if exists tenant_isolation on {$table}");
        $this->db->statement(sprintf(
            'create policy tenant_isolation on %s as permissive for all using (%s) with check (%s)',
            $table,
            self::TENANT_PREDICATE,
            self::TENANT_PREDICATE,
        ));
    }

    /** Full DML for ordinary tables. */
    public function grantCrud(string $table): void
    {
        $this->grant($table, ['select', 'insert', 'update', 'delete']);
    }

    /** Append-only tables (audit, integration call log): no UPDATE/DELETE/TRUNCATE. */
    public function grantAppendOnly(string $table): void
    {
        $this->grant($table, ['select', 'insert']);
    }

    /** @param list<string> $privileges */
    public function grant(string $table, array $privileges): void
    {
        self::assertIdentifier($table);
        $allowed = ['select', 'insert', 'update', 'delete'];
        foreach ($privileges as $p) {
            if (! in_array($p, $allowed, true)) {
                throw new InvalidArgumentException("Privilege {$p} is not grantable to the runtime role.");
            }
        }
        $this->db->statement("revoke all on {$table} from {$this->appRole}");
        $this->db->statement(sprintf('grant %s on %s to %s', implode(', ', $privileges), $table, $this->appRole));
    }

    public function grantSequence(string $sequence): void
    {
        self::assertIdentifier($sequence);
        $this->db->statement("grant usage, select on sequence {$sequence} to {$this->appRole}");
    }

    /**
     * Reject UPDATE, DELETE and TRUNCATE at the database (FR-AUD-003). Applies
     * to every role including the owner; only a superuser can bypass it, which
     * the hash chain then detects (D-021).
     */
    public function immutable(string $table): void
    {
        self::assertIdentifier($table);
        $this->db->statement(<<<'SQL'
            create or replace function fundly_reject_mutation() returns trigger
            language plpgsql as $$
            begin
                raise exception 'table % is append-only: % rejected', tg_table_name, tg_op
                    using errcode = 'insufficient_privilege';
            end;
            $$
        SQL);
        $this->db->statement("drop trigger if exists {$table}_immutable on {$table}");
        $this->db->statement("create trigger {$table}_immutable before update or delete on {$table} for each row execute function fundly_reject_mutation()");
        $this->db->statement("drop trigger if exists {$table}_no_truncate on {$table}");
        $this->db->statement("create trigger {$table}_no_truncate before truncate on {$table} for each statement execute function fundly_reject_mutation()");
    }

    private static function assertIdentifier(string $name): void
    {
        if (preg_match('/^[a-z_][a-z0-9_]{0,62}$/', $name) !== 1) {
            throw new InvalidArgumentException("Unsafe SQL identifier: {$name}");
        }
    }
}

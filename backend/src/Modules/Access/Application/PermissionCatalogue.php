<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Application;

use DateTimeImmutable;
use Fundly\Modules\Access\Domain\Permission;
use Illuminate\Database\ConnectionInterface;

/**
 * Syncs the code-defined permission catalogue into the `permissions` table
 * (runs as the schema owner after every migration). Permissions removed from
 * code are kept if still referenced, so history stays intact.
 */
final class PermissionCatalogue
{
    public static function sync(ConnectionInterface $owner): int
    {
        $now = new DateTimeImmutable;
        foreach (Permission::cases() as $p) {
            $owner->table('permissions')->upsert([[
                'code' => $p->value,
                'resource' => $p->resource(),
                'action' => $p->actionName(),
                'module' => $p->module(),
                'description' => $p->description(),
                'is_sensitive' => $p->isSensitive(),
                'synced_at' => $now,
            ]], ['code'], ['resource', 'action', 'module', 'description', 'is_sensitive', 'synced_at']);
        }

        return count(Permission::cases());
    }
}

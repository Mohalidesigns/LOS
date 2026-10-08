<?php

declare(strict_types=1);

namespace Fundly\Modules\Access\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;

/**
 * Has this actor already exercised one of these permissions on this entity?
 * Read from the audit trail, the authoritative record of who did what
 * (action-time SoD, FR-SEC-006).
 */
final class ActionHistory
{
    public function __construct(private readonly ConnectionInterface $db) {}

    /** @param list<string> $permissions */
    public function actorExercised(string $actorId, string $entityType, string $entityId, array $permissions): bool
    {
        if ($permissions === []) {
            return false;
        }

        return $this->db->table('audit_events')
            ->where('actor_id', $actorId)
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->where('outcome', 'success')
            ->whereIn('permission', $permissions)
            ->exists();
    }
}

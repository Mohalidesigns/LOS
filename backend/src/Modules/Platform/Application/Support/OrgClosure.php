<?php

declare(strict_types=1);

namespace Fundly\Modules\Platform\Application\Support;

use Fundly\Shared\Tenancy\TenantContext;
use Illuminate\Database\ConnectionInterface;

/**
 * Maintains the org_unit_closure table (TRD §2.3): one row per
 * (ancestor, descendant) pair including self at depth 0.
 */
final class OrgClosure
{
    public function __construct(private readonly ConnectionInterface $db, private readonly TenantContext $tenant) {}

    public function insertNode(string $id, ?string $parentId): void
    {
        $tenant = $this->tenant->requireId();
        $this->db->insert('insert into org_unit_closure (tenant_id, ancestor_id, descendant_id, depth) values (?, ?, ?, 0)', [$tenant, $id, $id]);
        if ($parentId !== null) {
            $this->db->insert(
                'insert into org_unit_closure (tenant_id, ancestor_id, descendant_id, depth)
                 select tenant_id, ancestor_id, ?, depth + 1 from org_unit_closure where descendant_id = ?',
                [$id, $parentId],
            );
        }
    }

    /** Re-parent a subtree: drop links from outside ancestors, then relink under the new parent. */
    public function move(string $id, ?string $newParentId): void
    {
        $this->db->delete(
            'delete from org_unit_closure c
              where c.descendant_id in (select descendant_id from org_unit_closure where ancestor_id = ?)
                and c.ancestor_id not in (select descendant_id from org_unit_closure where ancestor_id = ?)',
            [$id, $id],
        );
        if ($newParentId !== null) {
            $this->db->insert(
                'insert into org_unit_closure (tenant_id, ancestor_id, descendant_id, depth)
                 select super.tenant_id, super.ancestor_id, sub.descendant_id, super.depth + sub.depth + 1
                   from org_unit_closure super cross join org_unit_closure sub
                  where super.descendant_id = ? and sub.ancestor_id = ?',
                [$newParentId, $id],
            );
        }
        // Keep the denormalised depth on org_units in step with the closure.
        $this->db->update(
            'update org_units o set depth = (select count(*) - 1 from org_unit_closure c where c.descendant_id = o.id), updated_at = now()
              where o.id in (select descendant_id from org_unit_closure where ancestor_id = ?)',
            [$id],
        );
    }

    public function isDescendant(string $candidate, string $of): bool
    {
        return $this->db->table('org_unit_closure')->where('ancestor_id', $of)->where('descendant_id', $candidate)->exists();
    }

    /** Depth of the deepest node in the subtree, relative to its root. */
    public function subtreeHeight(string $id): int
    {
        return (int) $this->db->table('org_unit_closure')->where('ancestor_id', $id)->max('depth');
    }

    /** @return list<string> */
    public function subtree(string $id): array
    {
        /** @var list<string> $ids */
        $ids = $this->db->table('org_unit_closure')->where('ancestor_id', $id)->orderBy('depth')->pluck('descendant_id')->all();

        return $ids;
    }
}

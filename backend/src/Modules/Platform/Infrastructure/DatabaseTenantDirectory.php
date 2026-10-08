<?php

declare(strict_types=1);

namespace Fundly\Modules\Platform\Infrastructure;

use Fundly\Modules\Platform\Contracts\TenantDirectory;
use Illuminate\Database\ConnectionInterface;

final class DatabaseTenantDirectory implements TenantDirectory
{
    public function __construct(private readonly ConnectionInterface $db, private readonly string $mode) {}

    public function resolveForGuest(string $host): ?string
    {
        $byHost = $this->db->table('tenants')->where('status', 'active')->where('hostname', strtolower($host))->value('id');
        if (is_string($byHost)) {
            return $byHost;
        }
        if ($this->mode !== 'single') {
            return null;
        }
        $active = $this->db->table('tenants')->where('status', 'active')->limit(2)->pluck('id')->all();

        return count($active) === 1 && is_string($active[0]) ? $active[0] : null;
    }

    public function isActive(string $tenantId): bool
    {
        return $this->db->table('tenants')->where('id', $tenantId)->where('status', 'active')->exists();
    }

    public function activeTenantIds(): array
    {
        /** @var list<string> $ids */
        $ids = $this->db->table('tenants')->where('status', 'active')->orderBy('id')->pluck('id')->all();

        return $ids;
    }
}

<?php

declare(strict_types=1);

namespace Fundly\Modules\Platform\Infrastructure;

use Fundly\Modules\Platform\Contracts\ActiveConfiguration;
use Fundly\Shared\Tenancy\TenantContext;
use Illuminate\Database\ConnectionInterface;

final class DatabaseActiveConfiguration implements ActiveConfiguration
{
    public function __construct(private readonly ConnectionInterface $db, private readonly TenantContext $tenant)
    {
    }

    public function content(string $type, string $key): ?array
    {
        if (! $this->tenant->has()) {
            return null;
        }
        $json = $this->db->table('config_artifacts as a')
            ->join('config_versions as v', 'v.id', '=', 'a.active_version_id')
            ->where('a.type', $type)->where('a.key', $key)
            ->value('v.content');
        if (! is_string($json)) {
            return null;
        }
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }
}

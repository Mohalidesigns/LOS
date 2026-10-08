<?php

declare(strict_types=1);

namespace Fundly\Modules\Platform\Infrastructure;

use DateTimeImmutable;
use Fundly\Modules\Platform\Contracts\ActiveConfiguration;
use Fundly\Modules\Platform\Contracts\ConfigVersionView;
use Fundly\Shared\Tenancy\TenantContext;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;

final class DatabaseActiveConfiguration implements ActiveConfiguration
{
    public function __construct(private readonly ConnectionInterface $db, private readonly TenantContext $tenant) {}

    public function content(string $type, string $key): ?array
    {
        return $this->activeVersion($type, $key)?->content;
    }

    public function activeVersion(string $type, string $key): ?ConfigVersionView
    {
        if (! $this->tenant->has()) {
            return null;
        }

        return $this->map($this->base()->whereColumn('v.id', 'a.active_version_id')->where('a.type', $type)->where('a.key', $key)->first());
    }

    public function activeVersions(string $type): array
    {
        if (! $this->tenant->has()) {
            return [];
        }
        $out = [];
        foreach ($this->base()->whereColumn('v.id', 'a.active_version_id')->where('a.type', $type)->orderBy('a.key')->get() as $row) {
            $view = $this->map($row);
            if ($view !== null) {
                $out[] = $view;
            }
        }

        return $out;
    }

    public function version(string $versionId): ?ConfigVersionView
    {
        if (! $this->tenant->has() || preg_match('/^[0-9a-f-]{36}$/', $versionId) !== 1) {
            return null;
        }

        return $this->map($this->base()->where('v.id', $versionId)->first());
    }

    private function base(): Builder
    {
        return $this->db->table('config_versions as v')
            ->join('config_artifacts as a', 'a.id', '=', 'v.artifact_id')
            ->select(['v.id', 'v.artifact_id', 'a.type', 'a.key', 'a.name', 'v.version_no', 'v.status', 'v.content', 'v.activated_at']);
    }

    private function map(?object $row): ?ConfigVersionView
    {
        if ($row === null) {
            return null;
        }
        $r = get_object_vars($row);
        $content = json_decode(is_string($r['content'] ?? null) ? $r['content'] : '', true);

        return new ConfigVersionView(
            id: (string) $r['id'],
            artifactId: (string) $r['artifact_id'],
            type: (string) $r['type'],
            key: (string) $r['key'],
            name: (string) $r['name'],
            versionNo: (int) $r['version_no'],
            status: (string) $r['status'],
            content: is_array($content) ? $content : [],
            activatedAt: is_string($r['activated_at'] ?? null) ? new DateTimeImmutable($r['activated_at']) : null,
        );
    }
}

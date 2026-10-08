<?php

declare(strict_types=1);

namespace Fundly\Modules\Audit\Application;

use Fundly\Shared\Clock\Clock;
use Fundly\Shared\Database\Row;
use Fundly\Shared\Id\UuidV7;
use Fundly\Shared\Tenancy\TenantContext;
use Illuminate\Database\ConnectionInterface;

/**
 * Anchors each tenant's chain head (FR-AUD-004, TRD §5.4). In P0 the anchor
 * goes to `audit_checkpoints` (append-only, trigger-protected) and to a
 * write-once file sink standing in for the Object-Lock bucket / SIEM.
 */
final class AuditCheckpointer
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly TenantContext $tenant,
        private readonly Clock $clock,
        private readonly ?string $fileSinkPath,
    ) {}

    /** @return array{tenant_id: string, seq: int, hash: string}|null null if the chain is empty or unchanged */
    public function checkpoint(string $tenantId): ?array
    {
        return $this->tenant->run($tenantId, function () use ($tenantId): ?array {
            $head = Row::one($this->db->selectOne('select seq, hash from audit_events where tenant_id = ? order by seq desc limit 1', [$tenantId]));
            if ($head === null) {
                return null;
            }
            $last = Row::one($this->db->selectOne('select seq from audit_checkpoints where tenant_id = ? order by seq desc limit 1', [$tenantId]));
            if ($last !== null && (int) $last->seq === (int) $head->seq) {
                return null;
            }
            $now = $this->clock->now();
            $row = ['tenant_id' => $tenantId, 'seq' => (int) $head->seq, 'hash' => (string) $head->hash];
            $this->db->table('audit_checkpoints')->insert($row + ['id' => UuidV7::generate(), 'sink' => $this->fileSinkPath === null ? 'database' : 'database+file', 'created_at' => $now]);

            if ($this->fileSinkPath !== null) {
                if (! is_dir($this->fileSinkPath)) {
                    mkdir($this->fileSinkPath, 0750, true);
                }
                $file = sprintf('%s/%s-%012d.json', rtrim($this->fileSinkPath, '/'), $tenantId, $row['seq']);
                if (! file_exists($file)) {
                    file_put_contents($file, json_encode($row + ['anchored_at' => $now->format(DATE_ATOM)], JSON_THROW_ON_ERROR));
                    chmod($file, 0440);
                }
            }

            return $row;
        });
    }
}

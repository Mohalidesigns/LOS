<?php

declare(strict_types=1);

namespace Fundly\Integration\Adapters\OfflineLicence;

use Fundly\Shared\Database\Row;
use Fundly\Shared\Id\UuidV7;
use Illuminate\Database\ConnectionInterface;

/**
 * installation_fingerprint = SHA-256(installation UUID ‖ ":" ‖ PostgreSQL
 * system identifier) (TRD §2.6). The UUID is created once on first use; the
 * system identifier changes if the database cluster is re-initialised, so a
 * licence cannot be copied to another installation.
 */
final class InstallationIdentity
{
    private ?string $fingerprint = null;

    public function __construct(private readonly ConnectionInterface $db, private readonly string $environment) {}

    public function installationUuid(): string
    {
        $uuid = $this->db->table('installation')->value('installation_uuid');
        if (is_string($uuid)) {
            return $uuid;
        }
        $new = UuidV7::generate();
        $this->db->table('installation')->insertOrIgnore(['id' => UuidV7::generate(), 'installation_uuid' => $new, 'environment' => $this->environment]);
        $uuid = $this->db->table('installation')->orderBy('created_at')->value('installation_uuid');

        return is_string($uuid) ? $uuid : $new;
    }

    public function systemIdentifier(): string
    {
        $row = Row::one($this->db->selectOne('select system_identifier::text as id from pg_control_system()'));

        return $row !== null ? (string) $row->id : 'unknown';
    }

    public function fingerprint(): string
    {
        return $this->fingerprint ??= hash('sha256', $this->installationUuid().':'.$this->systemIdentifier());
    }
}
